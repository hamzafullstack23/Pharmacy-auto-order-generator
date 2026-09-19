<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Medicine;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class ResolveImportMappings
{
    /**
     * Authoritative chain:
     *   CSV.product_code → medicines → (id, company_id)
     *   medicines.id → medicine_supplier.supplier_id
     */
    public function resolveBatch(ImportBatch $batch): array
    {
        $missingMedicines  = [];
        $unlinkedMedicines = [];
        $affectedRows      = [];

        $batch->pendingRows()
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (
                &$missingMedicines, &$unlinkedMedicines, &$affectedRows
            ) {
                foreach ($rows as $row) {
                    $result = $this->resolveRow($row);

                    if ($result['resolved']) {
                        $row->update([
                            'medicine_id' => $result['medicine_id'],
                            'supplier_id' => $result['supplier_id'],
                            'company_id'  => $result['company_id'],
                            'status'      => 'resolved',
                        ]);
                        continue;
                    }

                    $affectedRows[] = [
                        'row_id'       => $row->id,
                        'row_number'   => $row->row_number,
                        'product_code' => $row->product_code,
                        'product_name' => $row->product_name,
                        'quantity'     => (float) $row->quantity,
                        'sale_date'    => optional($row->sale_date)->format('Y-m-d'),
                        'reason'       => $result['reason'],
                    ];

                    if (!empty($result['missing_medicine'])) {
                        $missingMedicines[$row->product_code] = [
                            'product_code' => $row->product_code,
                            'product_name' => $row->product_name,
                            'occurrences'  => ($missingMedicines[$row->product_code]['occurrences'] ?? 0) + 1,
                        ];
                    }

                    if (!empty($result['unlinked_medicine_id'])) {
                        $mid = $result['unlinked_medicine_id'];
                        $unlinkedMedicines[$mid] = [
                            'medicine_id'   => $mid,
                            'medicine_name' => $result['unlinked_medicine_name'] ?? null,
                            'product_code'  => $row->product_code,
                            'occurrences'   => ($unlinkedMedicines[$mid]['occurrences'] ?? 0) + 1,
                        ];
                    }
                }
            });

        // INTERPRETATION A: return only the first of each kind
        return [
            'missing_medicines'  => $missingMedicines  ? [array_values($missingMedicines)[0]]  : [],
            'unlinked_medicines' => $unlinkedMedicines ? [array_values($unlinkedMedicines)[0]] : [],
            'total_missing_medicines'  => count($missingMedicines),
            'total_unlinked_medicines' => count($unlinkedMedicines),
            'affected_rows'      => $affectedRows,
        ];
    }

    protected function resolveRow(ImportRow $row): array
    {
        if ($row->medicine_id && $row->supplier_id) {
            return [
                'resolved'    => true,
                'medicine_id' => $row->medicine_id,
                'supplier_id' => $row->supplier_id,
                'company_id'  => $row->company_id,
            ];
        }

        // Step 1: CSV.product_code → medicines
        $medicine = Medicine::where('product_code', $row->product_code)->first();

        if (!$medicine) {
            $trimmed = ltrim($row->product_code, '0');
            if ($trimmed !== $row->product_code) {
                $medicine = Medicine::where('product_code', $trimmed)->first();
            }
        }
        if (!$medicine) {
            $medicine = Medicine::whereRaw('UPPER(product_code) = ?', [strtoupper($row->product_code)])->first();
        }

        if (!$medicine) {
            return [
                'resolved'         => false,
                'reason'           => 'medicine_not_found',
                'missing_medicine' => true,
            ];
        }

        // Step 2: medicine → medicine_supplier
        $supplierId = DB::table('medicine_supplier')
            ->where('medicine_id', $medicine->id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->value('supplier_id');

        if (!$supplierId) {
            return [
                'resolved'               => false,
                'reason'                 => 'supplier_link_missing',
                'medicine_id'            => $medicine->id,
                'unlinked_medicine_id'   => $medicine->id,
                'unlinked_medicine_name' => $medicine->name,
            ];
        }

        // Step 3: company_id read from medicine (authoritative)
        return [
            'resolved'    => true,
            'medicine_id' => $medicine->id,
            'supplier_id' => (int) $supplierId,
            'company_id'  => $medicine->company_id,   // may be null
        ];
    }

    /**
     * Link a supplier to a medicine (used when supplier_link_missing).
     */
    public function applyResolutions(ImportBatch $batch, array $resolutions): array
    {
        DB::transaction(function () use ($resolutions) {
            foreach ($resolutions['medicine_suppliers'] ?? [] as $medicineId => $supplierId) {
                if (empty($supplierId)) continue;
                DB::table('medicine_supplier')->updateOrInsert(
                    ['medicine_id' => $medicineId, 'supplier_id' => $supplierId],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        return $this->resolveBatch($batch);
    }

    /**
     * Handle a single missing medicine: either CREATE it or SKIP it.
     *
     * Interpretation A:
     *  - Pick from dropdown  → use the existing record.
     *  - Type a name         → ALWAYS create a NEW supplier / company.
     *  - On create, bind the medicine to the supplier via medicine_supplier
     *    immediately, and set the medicine's company_id.
     */
    public function resolveMedicine(
        ImportBatch $batch,
        string $productCode,
        string $action,
        ?int $companyId = null,
        ?int $supplierId = null,
        ?string $newCompany = null,
        ?string $newSupplier = null
    ): array {
        DB::transaction(function () use (
            $batch, $productCode, $action,
            $companyId, $supplierId,
            $newCompany, $newSupplier
        ) {
            // ---------- SKIP ----------
            if ($action === 'skip') {
                ImportRow::where('import_batch_id', $batch->id)
                    ->where('product_code', $productCode)
                    ->where('status', 'pending')
                    ->update([
                        'status'        => 'skipped',
                        'error_message' => 'Skipped by user (medicine not found)',
                    ]);
                return;
            }

            if ($action !== 'create') {
                throw new \InvalidArgumentException("Unknown action: {$action}");
            }

            /* ---------- 1. Resolve SUPPLIER ---------- */
            $supplier = null;

            if ($supplierId) {
                // Picked from dropdown
                $supplier = Supplier::find($supplierId);
                if (!$supplier) {
                    throw new \RuntimeException("Supplier #{$supplierId} not found.");
                }
            } elseif ($newSupplier !== null && trim($newSupplier) !== '') {
                // Typed name → ALWAYS create a new supplier
                $supplier = Supplier::create([
                    'name'      => trim($newSupplier),
                    'is_active' => true,
                ]);
            }

            if (!$supplier) {
                throw new \InvalidArgumentException('Supplier is required to create a medicine.');
            }

            /* ---------- 2. Resolve COMPANY ---------- */
            $company = null;

            if ($companyId) {
                // Picked from dropdown
                $company = Company::find($companyId);
                if (!$company) {
                    throw new \RuntimeException("Company #{$companyId} not found.");
                }

                // Attach the supplier if the company has none
                if (empty($company->supplier_id)) {
                    $company->update(['supplier_id' => $supplier->id]);
                }
            } elseif ($newCompany !== null && trim($newCompany) !== '') {
                // Typed name → create a new company attached to the supplier
                $company = Company::create([
                    'name'        => trim($newCompany),
                    'supplier_id' => $supplier->id,
                    'is_active'   => true,
                ]);
            }

            if (!$company) {
                throw new \InvalidArgumentException('Company is required to create a medicine.');
            }

            /* ---------- 3. Sample row for product_name ---------- */
            $sample = ImportRow::where('import_batch_id', $batch->id)
                ->where('product_code', $productCode)
                ->first();

            if (!$sample) {
                throw new \RuntimeException("No import rows found for product code {$productCode}.");
            }

            /* ---------- 4. Create the medicine (idempotent) ---------- */
            $medicine = Medicine::where('product_code', $productCode)->first();

            if (!$medicine) {
                $medicine = Medicine::create([
                    'product_code' => $sample->product_code,
                    'name'         => $sample->product_name,
                    'company_id'   => $company->id,
                    'pack_type'    => 'loose',
                    'current_stock'=> 0,
                    'is_active'    => true,
                ]);
            }

            /* ---------- 5. Bind medicine ↔ supplier (pivot) ---------- */
            $pivotExists = DB::table('medicine_supplier')
                ->where('medicine_id', $medicine->id)
                ->where('supplier_id', $supplier->id)
                ->exists();

            if (!$pivotExists) {
                DB::table('medicine_supplier')->insert([
                    'medicine_id' => $medicine->id,
                    'supplier_id' => $supplier->id,
                    'is_primary'  => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            /* ---------- 6. Mark all pending rows of this code as resolved ---------- */
            ImportRow::where('import_batch_id', $batch->id)
                ->where('product_code', $productCode)
                ->where('status', 'pending')
                ->update([
                    'medicine_id' => $medicine->id,
                    'supplier_id' => $supplier->id,
                    'company_id'  => $company->id,
                    'status'      => 'resolved',
                ]);
        });

        return $this->resolveBatch($batch);
    }
}
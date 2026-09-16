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
     *   product_code → medicines.id
     *                → medicine_supplier.supplier_id
     *                → companies.supplier_id → companies.id
     */
    public function resolveBatch(ImportBatch $batch): array
    {
        $missingMedicines  = [];
        $missingSuppliers  = [];
        $missingCompanies  = [];
        $unlinkedMedicines = [];
        $suppliersWithoutCompany = [];
        $affectedRows = [];

        $batch->pendingRows()
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (
                &$missingMedicines, &$missingSuppliers, &$missingCompanies,
                &$unlinkedMedicines, &$suppliersWithoutCompany, &$affectedRows
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
                        'medicine_id'  => $result['medicine_id'] ?? null,
                        'supplier_id'  => $result['supplier_id'] ?? null,
                        'company_id'   => $result['company_id'] ?? null,
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

                    if (!empty($result['missing_supplier_id'])) {
                        $sid = $result['missing_supplier_id'];
                        $missingSuppliers[$sid] = [
                            'supplier_id' => $sid,
                            'occurrences' => ($missingSuppliers[$sid]['occurrences'] ?? 0) + 1,
                        ];
                    }

                    if (!empty($result['supplier_without_company_id'])) {
                        $sid = $result['supplier_without_company_id'];
                        $suppliersWithoutCompany[$sid] = [
                            'supplier_id'   => $sid,
                            'supplier_name' => $result['supplier_without_company_name'] ?? null,
                            'occurrences'   => ($suppliersWithoutCompany[$sid]['occurrences'] ?? 0) + 1,
                        ];
                    }

                    if (!empty($result['missing_company_id'])) {
                        $cid = $result['missing_company_id'];
                        $missingCompanies[$cid] = [
                            'company_id'  => $cid,
                            'occurrences' => ($missingCompanies[$cid]['occurrences'] ?? 0) + 1,
                        ];
                    }
                }
            });

        return [
            'missing_medicines'         => array_values($missingMedicines),
            'unlinked_medicines'        => array_values($unlinkedMedicines),
            'missing_suppliers'         => array_values($missingSuppliers),
            'suppliers_without_company' => array_values($suppliersWithoutCompany),
            'missing_companies'         => array_values($missingCompanies),
            'affected_rows'             => $affectedRows,
        ];
    }

    protected function resolveRow(ImportRow $row): array
    {
        // Already resolved at row level? Trust it.
        if ($row->medicine_id && $row->supplier_id && $row->company_id) {
            return [
                'resolved'            => true,
                'medicine_id'         => $row->medicine_id,
                'supplier_id'         => $row->supplier_id,
                'company_id'          => $row->company_id,
                'missing_medicine'    => false,
                'missing_supplier_id' => null,
                'missing_company_id'  => null,
            ];
        }

        // Step 1: product_code → medicine
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
                'resolved'            => false,
                'reason'              => 'medicine_not_found',
                'missing_medicine'    => true,
                'missing_supplier_id' => null,
                'missing_company_id'  => null,
            ];
        }

        // Step 2: medicine → medicine_supplier.supplier_id
        $supplierId = DB::table('medicine_supplier')
            ->where('medicine_id', $medicine->id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->value('supplier_id');

        if (!$supplierId) {
            return [
                'resolved'               => false,
                'reason'                 => 'supplier_link_missing',
                'missing_medicine'       => false,
                'missing_supplier_id'    => null,
                'missing_company_id'     => null,
                'medicine_id'            => $medicine->id,
                'unlinked_medicine_id'   => $medicine->id,
                'unlinked_medicine_name' => $medicine->name,
            ];
        }

        // Step 3: supplier exists?
        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            return [
                'resolved'            => false,
                'reason'              => 'supplier_not_found',
                'missing_medicine'    => false,
                'missing_supplier_id' => $supplierId,
                'missing_company_id'  => null,
                'medicine_id'         => $medicine->id,
            ];
        }

        // Step 4: supplier → companies.supplier_id → company.id
        $company = Company::where('supplier_id', $supplierId)
            ->orderBy('id')
            ->first();

        if (!$company) {
            return [
                'resolved'                     => false,
                'reason'                       => 'supplier_has_no_company',
                'missing_medicine'             => false,
                'missing_supplier_id'          => null,
                'missing_company_id'           => null,
                'medicine_id'                  => $medicine->id,
                'supplier_id'                  => $supplier->id,
                'supplier_without_company_id'  => $supplier->id,
                'supplier_without_company_name'=> $supplier->name,
            ];
        }

        // Fully resolved
        return [
            'resolved'            => true,
            'medicine_id'         => $medicine->id,
            'supplier_id'         => $supplier->id,
            'company_id'          => $company->id,
            'missing_medicine'    => false,
            'missing_supplier_id' => null,
            'missing_company_id'  => null,
        ];
    }

    /**
     * Apply user resolutions, then re-run the resolver.
     *
     * $resolutions:
     *   medicine_suppliers     => [ medicine_id => supplier_id, ... ]   (link medicine → supplier via pivot)
     *   supplier_company_links => [ supplier_id => company_id, ... ]    (reassign a company's supplier_id)
     *   new_companies          => [ [ 'name' => ..., 'supplier_id' => ... ], ... ]
     */
    public function applyResolutions(ImportBatch $batch, array $resolutions): array
    {
        DB::transaction(function () use ($resolutions) {
            // 1. Link medicine → supplier via pivot
            foreach ($resolutions['medicine_suppliers'] ?? [] as $medicineId => $supplierId) {
                if (empty($supplierId)) continue;

                DB::table('medicine_supplier')->updateOrInsert(
                    ['medicine_id' => $medicineId, 'supplier_id' => $supplierId],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            // 2. Reassign an existing company to a supplier
            foreach ($resolutions['supplier_company_links'] ?? [] as $supplierId => $companyId) {
                if (empty($companyId)) continue;
                Company::where('id', $companyId)->update(['supplier_id' => $supplierId]);
            }

            // 3. Create brand new companies attached to a supplier
            foreach ($resolutions['new_companies'] ?? [] as $payload) {
                if (empty($payload['name'])) continue;

                Company::create([
                    'name'        => $payload['name'],
                    'supplier_id' => $payload['supplier_id'] ?? null,
                    'is_active'   => true,
                ]);
            }
        });

        return $this->resolveBatch($batch);
    }
}
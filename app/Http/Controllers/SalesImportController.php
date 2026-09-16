<?php

namespace App\Http\Controllers;

use App\Imports\DailySalesImport;
use App\Models\Company;
use App\Models\DailySale;
use App\Models\ImportBatch;
use App\Models\Medicine;
use App\Models\Supplier;
use App\Services\ResolveImportMappings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

class SalesImportController extends Controller
{
    public function __construct(
        protected ResolveImportMappings $resolver
    ) {}

    public function showUploadForm()
    {
        return view('sales.upload');
    }

    /**
     * Phase 1: Upload, stage, and attempt resolution.
     */
    public function upload(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $request->validate([
            'file'      => 'required|file|max:102400|mimes:csv,xlsx,xls',
            'sale_date' => 'required|date|before_or_equal:today',
        ]);

        $storedPath = null;

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $storedPath = $file->storeAs('imports', 'sales_import_' . time() . '.' . $extension, 'local');
            $fullPath = Storage::disk('local')->path($storedPath);

            $batch = ImportBatch::create([
                'batch_uuid'        => (string) Str::uuid(),
                'status'            => 'staged',
                'sale_date'         => $request->sale_date,
                'original_filename' => $file->getClientOriginalName(),
            ]);

            $import = new DailySalesImport($request->sale_date, $batch);

            if ($extension === 'csv') {
                Excel::import($import, $fullPath, null, ExcelType::CSV);
            } else {
                Excel::import($import, $fullPath);
            }

            Storage::disk('local')->delete($storedPath);
            $storedPath = null;

            $resolution = $this->resolver->resolveBatch($batch);

            $batch->update([
                'stats' => [
                    'staged'  => $import->getStagedCount(),
                    'skipped' => $import->getSkippedCount(),
                    'errors'  => $import->getErrors(),
                ],
            ]);

            return $this->buildPauseOrCommitResponse($batch, $resolution);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            if ($storedPath) Storage::disk('local')->delete($storedPath);
            $errors = [];
            foreach ($e->failures() as $failure) {
                $errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }
            return response()->json([
                'status' => 'failed',
                'errors' => array_slice($errors, 0, 50),
                'total'  => count($errors),
            ], 422);
        } catch (\Exception $e) {
            if ($storedPath) Storage::disk('local')->delete($storedPath);
            Log::error('Sales import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'failed', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Phase 4: Apply user resolutions and resume.
     */
    // public function resume(Request $request, string $batchUuid)
    // {
    //     $request->headers->set('Accept', 'application/json');

    //     $batch = ImportBatch::where('batch_uuid', $batchUuid)->firstOrFail();

    //     if ($batch->status === 'committed') {
    //         return response()->json([
    //             'status'  => 'completed',
    //             'stats'   => $batch->stats,
    //             'message' => 'This batch was already committed.',
    //         ]);
    //     }

    //     $resolutions = $request->validate([
    //         'suppliers'                => 'array',
    //         'suppliers.*.company_id'   => 'nullable|integer|exists:companies,id',
    //         'companies'                => 'array',
    //         'companies.*.name'         => 'nullable|string|max:255',
    //         'companies.*.code'         => 'nullable|string|max:100',
    //     ]);

    //     $resolution = $this->resolver->applyResolutions($batch, $resolutions);

    //     $batch->update(['missing_entities' => $resolution]);

    //     if (!empty($resolution['missing_medicines'])
    //         || !empty($resolution['missing_suppliers'])
    //         || !empty($resolution['missing_companies'])
    //     ) {
    //         return response()->json([
    //             'status'            => 'paused',
    //             'batch'             => $batch->batch_uuid,
    //             'missing_medicines' => $resolution['missing_medicines'],
    //             'missing_suppliers' => $resolution['missing_suppliers'],
    //             'missing_companies' => $resolution['missing_companies'],
    //             'affected_rows'     => $resolution['affected_rows'],
    //             'message'           => 'Some mappings are still unresolved.',
    //         ]);
    //     }

    //     $commitStats = $this->commitBatch($batch);

    //     return response()->json([
    //         'status'  => 'completed',
    //         'batch'   => $batch->batch_uuid,
    //         'stats'   => $commitStats,
    //         'message' => 'Import completed successfully.',
    //     ]);
    // }

    public function searchSuppliers(Request $request)
    {
        $q = $request->input('q', '');

        $suppliers = Supplier::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'LIKE', "%{$q}%")
                        // ->orWhere('code', 'LIKE', "%{$q}%")
                        ->orWhere('id', $q);
                });
            })
            ->with('company:id,name')
            ->limit(25)
            ->get(['id', 'name', 'company_id']);

        return response()->json($suppliers);
    }

    public function searchCompanies(Request $request)
    {
        $q = $request->input('q', '');

        $companies = Company::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'LIKE', "%{$q}%")
                        // ->orWhere('code', 'LIKE', "%{$q}%")
                        ->orWhere('id', $q);
                });
            })
            ->limit(25)
            ->get(['id', 'name']);

        return response()->json($companies);
    }

    public function searchMedicines(Request $request)
    {
        $q = $request->input('q', '');

        $medicines = Medicine::query()
            ->when($q, function ($query) use ($q) {
                $query->where('product_code', 'LIKE', "%{$q}%")
                    ->orWhere('name', 'LIKE', "%{$q}%");
            })
            ->limit(25)
            ->get(['id', 'product_code', 'name']);

        return response()->json($medicines);
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $supplier = Supplier::create([
            'name'      => $data['name'],
            'is_active' => true,
        ]);

        return response()->json($supplier, 201);
    }

    public function storeCompany(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $company = Company::create([
            'name'      => $data['name'],
            'is_active' => true,
        ]);

        return response()->json($company, 201);
    }

    public function history()
    {
        $sales = DailySale::with(['company', 'supplier', 'medicine'])
            ->orderBy('sale_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('sales.history', compact('sales'));
    }

    protected function commitBatch(ImportBatch $batch): array
    {
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($batch, &$imported, &$skipped) {
            $batch->rows()
                ->where('status', 'resolved')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$imported) {
                    foreach ($rows as $row) {
                        $existing = DailySale::where('sale_date', $row->sale_date)
                            ->where('medicine_id', $row->medicine_id)
                            ->first();

                        if ($existing) {
                            $existing->update([
                                'quantity_sold' => $existing->quantity_sold + $row->quantity,
                                'updated_at'    => now(),
                            ]);
                        } else {
                            DailySale::create([
                                'sale_date'     => $row->sale_date,
                                'medicine_name' => $row->product_name,
                                'quantity_sold' => $row->quantity,
                                'company_id'    => $row->company_id,
                                'supplier_id'   => $row->supplier_id,
                                'medicine_id'   => $row->medicine_id,
                                'import_batch'  => $batch->batch_uuid,
                            ]);
                        }

                        $row->update(['status' => 'committed']);
                        $imported++;
                    }
                });

            $skipped = $batch->rows()->where('status', 'pending')->count();

            $batch->update([
                'status' => 'committed',
                'stats'  => array_merge($batch->stats ?? [], [
                    'imported' => $imported,
                    'skipped'  => $skipped,
                ]),
            ]);
        });

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
        ];
    }

    // In SalesImportController

    /**
     * Load all suppliers + companies for the resolve dropdowns.
     */
    public function resolveOptions()
    {
        return response()->json([
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Resume with the enriched resolution payload.
     */
    public function resume(Request $request, string $batchUuid)
    {
        $request->headers->set('Accept', 'application/json');

        $batch = ImportBatch::where('batch_uuid', $batchUuid)->firstOrFail();

        if ($batch->status === 'committed') {
            return response()->json([
                'status'  => 'completed',
                'stats'   => $batch->stats,
                'message' => 'This batch was already committed.',
            ]);
        }

        $resolutions = $request->validate([
            'medicine_suppliers'   => 'array',
            'medicine_suppliers.*' => 'nullable|integer|exists:suppliers,id',
        ]);

        $resolution = $this->resolver->applyResolutions($batch, $resolutions);
        $batch->update(['missing_entities' => $resolution]);

        return $this->buildPauseOrCommitResponse($batch, $resolution);
    }

    public function resolveMedicine(Request $request, string $batchUuid)
    {
        $request->headers->set('Accept', 'application/json');

        $data = $request->validate([
            'product_code' => 'required|string',
            'action'       => 'required|in:create,skip',
            'company_id'   => 'nullable|integer|exists:companies,id',
            'supplier_id'  => 'nullable|integer|exists:suppliers,id',
        ]);

        $batch = ImportBatch::where('batch_uuid', $batchUuid)->firstOrFail();

        try {
            $resolution = $this->resolver->resolveMedicine(
                $batch,
                $data['product_code'],
                $data['action'],
                $data['company_id'] ?? null,
                $data['supplier_id'] ?? null
            );

            $batch->update(['missing_entities' => $resolution]);

            return $this->buildPauseOrCommitResponse($batch, $resolution);
        } catch (\Throwable $e) {
            Log::error('resolveMedicine failed', ['message' => $e->getMessage()]);
            return response()->json(['status' => 'failed', 'message' => $e->getMessage()], 500);
        }
    }

    protected function buildPauseOrCommitResponse(ImportBatch $batch, array $resolution)
    {
        $hasUnresolved =
            !empty($resolution['missing_medicines'])
            || !empty($resolution['unlinked_medicines'])
            || !empty($resolution['supplier_link_missing'])
            || !empty($resolution['suppliers_without_company']);

        if (!$hasUnresolved) {
            $commitStats = $this->commitBatch($batch);
            return response()->json([
                'status'  => 'completed',
                'batch'   => $batch->batch_uuid,
                'stats'   => $commitStats,
                'message' => 'Import completed successfully.',
            ]);
        }

        $batch->update(['status' => 'paused']);

        return response()->json([
            'status'                    => 'paused',
            'batch'                     => $batch->batch_uuid,
            'missing_medicines'         => $resolution['missing_medicines'] ?? [],
            'unlinked_medicines'        => $resolution['unlinked_medicines'] ?? [],
            'total_missing_medicines'   => $resolution['total_missing_medicines'] ?? 0,
            'total_unlinked_medicines'  => $resolution['total_unlinked_medicines'] ?? 0,
            'affected_rows'             => $resolution['affected_rows'] ?? [],
            'stats'                     => $batch->stats,
            'message'                   => 'Please resolve the next item to continue.',
        ]);
    }
}

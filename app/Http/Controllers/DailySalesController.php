<?php

namespace App\Http\Controllers;

use App\Exports\SupplierDailySalesExport;
use App\Models\ExportBatch;
use App\Models\ImportRow;
use App\Models\Supplier;
use App\Services\SupplierExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

class DailySalesController extends Controller
{
    public function __construct(
        protected SupplierExportService $exportService
    ) {}

    /* ------------------------------------------------------------------
     |  Daily Sales list (from import_rows)
     * ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $query = ImportRow::query()
            ->with([
                'medicine:id,product_code,name',
                'supplier:id,name',
                'company:id,name',
                'batch:id,batch_uuid',
            ])
            ->whereIn('status', ['resolved', 'committed'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        // Filters
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }
        if ($request->filled('export_status')) {
            $query->where('is_exported', $request->export_status === 'exported');
        }

        $sales = $query->paginate(50)->withQueryString();

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        // Summary cards (reflect the same filters, without pagination)
        $summaryQuery = (clone $query);
        $totalRows     = $summaryQuery->count();
        $totalQty      = (clone $query)->sum('quantity');
        $unexportedQty = (clone $query)->where('is_exported', false)->sum('quantity');

        return view('sales.daily.index', compact(
            'sales',
            'suppliers',
            'totalRows',
            'totalQty',
            'unexportedQty'
        ));
    }

    /* ------------------------------------------------------------------
     |  Export form
     * ------------------------------------------------------------------ */
    public function showExportForm(Request $request)
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $selectedSupplier = null;
        $exportable = collect();
        $pending    = collect();

        if ($request->filled('supplier_id')) {
            $selectedSupplier = Supplier::find($request->supplier_id);

            if ($selectedSupplier) {
                $exportable = $this->exportService->exportableRows($selectedSupplier->id);
                $pending    = $this->exportService->pendingRows($selectedSupplier->id);
            }
        }

        return view('sales.daily.export', compact('suppliers', 'selectedSupplier', 'exportable', 'pending'));
    }

    /* ------------------------------------------------------------------
     |  Perform export
     * ------------------------------------------------------------------ */
    // public function export(Request $request)
    // {
    //     $request->validate([
    //         'supplier_id' => 'required|integer|exists:suppliers,id',
    //     ]);

    //     $supplier = Supplier::findOrFail($request->supplier_id);

    //     $rows = $this->exportService->aggregatedUnexportedRows($supplier->id);

    //     if ($rows->isEmpty()) {
    //         return back()->with('warning', "No un-exported rows found for supplier '{$supplier->name}'.");
    //     }

    //     $totalQty = (float) $rows->sum('total_quantity');

    //     $filename = 'daily-sales-' . Str::slug($supplier->name)
    //               . '-' . now()->format('Y-m-d-His') . '.csv';

    //     $batch = ExportBatch::create([
    //         'export_uuid'    => (string) Str::uuid(),
    //         'supplier_id'    => $supplier->id,
    //         'supplier_name'  => $supplier->name,
    //         'rows_count'     => $rows->count(),
    //         'total_quantity' => $totalQty,
    //         'filename'       => $filename,
    //     ]);

    //     $marked = $this->exportService->markExported($supplier->id, $batch);

    //     Log::info('Daily sales exported', [
    //         'supplier_id' => $supplier->id,
    //         'supplier'    => $supplier->name,
    //         'aggregated_rows' => $rows->count(),
    //         'marked_import_rows' => $marked,
    //         'total_quantity' => $totalQty,
    //         'batch_uuid'  => $batch->export_uuid,
    //     ]);

    //     return Excel::download(
    //         new SupplierDailySalesExport($supplier->name, $rows),
    //         $filename,
    //         ExcelType::CSV
    //     );
    // }
    public function export(Request $request)
{
    $request->validate([
        'supplier_id' => 'required|integer|exists:suppliers,id',
        'format'      => 'required|in:csv,xlsx',
    ]);

    $supplier = Supplier::findOrFail($request->supplier_id);
    $format   = $request->input('format', 'xlsx');

    $exportable = $this->exportService->exportableRows($supplier->id);

    if ($exportable->isEmpty()) {
        return back()->with('warning',
            "No products have reached a full pack for supplier '{$supplier->name}'.");
    }

    $totalPacks = $exportable->sum('full_packs');
    $totalUnits = $exportable->sum(fn($r) => $r->full_packs * $r->pack_size);

    $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
    $filename  = 'daily-sales-' . Str::slug($supplier->name)
               . '-' . now()->format('Y-m-d-His') . '.' . $extension;

    $batch = ExportBatch::create([
        'export_uuid'    => (string) Str::uuid(),
        'supplier_id'    => $supplier->id,
        'supplier_name'  => $supplier->name,
        'rows_count'     => $exportable->count(),
        'total_quantity' => $totalPacks,
        'filename'       => $filename,
    ]);

    foreach ($exportable as $row) {
        $this->exportService->markProductExported(
            $supplier->id,
            $batch,
            $row->product_code,
            $row->full_packs,
            $row->pack_size
        );
    }

    $writerType = $format === 'xlsx'
        ? \Maatwebsite\Excel\Excel::XLSX
        : \Maatwebsite\Excel\Excel::CSV;

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\SupplierDailySalesExport($supplier->name, $exportable),
        $filename,
        $writerType
    );
}
    /* ------------------------------------------------------------------
     |  Export history
     * ------------------------------------------------------------------ */
    public function history()
    {
        $batches = ExportBatch::with('supplier:id,name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('sales.daily.export-history', compact('batches'));
    }

    /**
     * Mark a single import row as "not received", returning it to the pending pool.
     */
    public function markNotReceived(Request $request, ImportRow $importRow)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        if (!$importRow->is_exported) {
            return back()->with('warning', 'This row is not currently marked as exported.');
        }

        $importRow->update([
            'is_exported'     => false,
            'exported_at'     => null,
            'export_batch_id' => null,
            'return_reason'   => $request->input('reason', 'Not received from supplier'),
            'returned_at'     => now(),
        ]);

        // If the export batch is now empty (all rows returned), delete the batch
        if ($importRow->export_batch_id) {
            $batchId = $importRow->getOriginal('export_batch_id');
            $remaining = ImportRow::where('export_batch_id', $batchId)->count();
            if ($remaining === 0) {
                ExportBatch::where('id', $batchId)->delete();
            }
        }

        return back()->with(
            'success',
            "Product '{$importRow->product_name}' returned to pending and will appear in the next export."
        );
    }

    /**
     * Mark multiple rows from an export batch as not received in one action.
     * Called from the export history detail view.
     */
    public function markBatchItemsNotReceived(Request $request, string $batchUuid)
    {
        $request->validate([
            'row_ids'   => 'required|array|min:1',
            'row_ids.*' => 'integer|exists:import_rows,id',
            'reason'    => 'nullable|string|max:255',
        ]);

        $batch = ExportBatch::where('export_uuid', $batchUuid)->firstOrFail();

        $updated = ImportRow::where('export_batch_id', $batch->id)
            ->whereIn('id', $request->row_ids)
            ->update([
                'is_exported'     => false,
                'exported_at'     => null,
                'export_batch_id' => null,
                'return_reason'   => $request->input('reason', 'Not received from supplier'),
                'returned_at'     => now(),
            ]);

        // Recalculate batch stats
        $batch->update([
            'rows_count'     => ImportRow::where('export_batch_id', $batch->id)->count(),
            'total_quantity' => ImportRow::where('export_batch_id', $batch->id)->sum('quantity'),
        ]);

        // Delete empty batch
        if ($batch->fresh()->rows_count === 0) {
            $batch->delete();
        }

        return back()->with(
            'success',
            "{$updated} product(s) returned to pending for the next export."
        );
    }

    public function showExportDetail(string $batchUuid)
    {
        $batch = ExportBatch::where('export_uuid', $batchUuid)->firstOrFail();

        // All rows ever attached to this batch (including returned ones)
        $rows = ImportRow::where(function ($q) use ($batch) {
            $q->where('export_batch_id', $batch->id)
                ->orWhere(function ($q2) use ($batch) {
                    // Historical: rows that were returned from this batch
                    $q2->whereNotNull('returned_at')
                        ->whereIn('product_code', function ($sub) use ($batch) {
                            $sub->select('product_code')
                                ->from('import_rows')
                                ->where('export_batch_id', $batch->id);
                        });
                });
        })
            ->orderBy('product_name')
            ->get();

        return view('sales.daily.export-detail', compact('batch', 'rows'));
    }
}

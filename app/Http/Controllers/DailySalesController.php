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
            'sales', 'suppliers', 'totalRows', 'totalQty', 'unexportedQty'
        ));
    }

    /* ------------------------------------------------------------------
     |  Export form
     * ------------------------------------------------------------------ */
    public function showExportForm(Request $request)
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $selectedSupplierId = $request->input('supplier_id');
        $selectedSupplier   = null;
        $preview            = collect();

        if ($selectedSupplierId) {
            $selectedSupplier = Supplier::find($selectedSupplierId);

            if ($selectedSupplier) {
                $preview = $this->exportService
                    ->aggregatedUnexportedRows($selectedSupplier->id);
            }
        }

        return view('sales.daily.export', compact('suppliers', 'selectedSupplier', 'preview'));
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

    $rows = $this->exportService->aggregatedUnexportedRows($supplier->id);

    if ($rows->isEmpty()) {
        return back()->with('warning', "No un-exported rows for '{$supplier->name}'.");
    }

    $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
    $filename  = 'daily-sales-' . Str::slug($supplier->name)
               . '-' . now()->format('Y-m-d-His') . '.' . $extension;

    $batch = ExportBatch::create([
        'export_uuid'    => (string) Str::uuid(),
        'supplier_id'    => $supplier->id,
        'supplier_name'  => $supplier->name,
        'rows_count'     => $rows->count(),
        'total_quantity' => (float) $rows->sum('total_quantity'),
        'filename'       => $filename,
    ]);

    $this->exportService->markExported($supplier->id, $batch);

    $writerType = $format === 'xlsx'
        ? \Maatwebsite\Excel\Excel::XLSX
        : \Maatwebsite\Excel\Excel::CSV;

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\SupplierDailySalesExport($supplier->name, $rows),
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
}
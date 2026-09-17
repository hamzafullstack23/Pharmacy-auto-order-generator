<?php

namespace App\Services;

use App\Models\ExportBatch;
use App\Models\ImportRow;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierExportService
{
    /**
     * Statuses considered "resolved enough to export".
     */
    public const EXPORTABLE_STATUSES = ['resolved', 'committed'];

    /**
     * Resolve the effective supplier_id for an import row.
     * Priority:
     *   1) import_rows.supplier_id (if set)
     *   2) join through medicine_supplier on medicine_id
     */
    public function effectiveSupplierIdSubquery()
    {
        // Used by the aggregation query below.
        return DB::table('medicine_supplier')
            ->select('supplier_id')
            ->whereColumn('medicine_supplier.medicine_id', 'import_rows.medicine_id')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->limit(1);
    }

    /**
     * Aggregate un-exported rows by product_code for a given supplier.
     * Returns:
     *  [ ['product_code' => ..., 'product_name' => ..., 'total_quantity' => ..., 'row_count' => ...], ... ]
     */
    public function aggregatedUnexportedRows(int $supplierId): Collection
    {
        return ImportRow::query()
            ->whereIn('status', self::EXPORTABLE_STATUSES)
            ->where('is_exported', false)
            ->where(function ($q) use ($supplierId) {
                // Supplier matches either directly, or via medicine_supplier fallback
                $q->where('supplier_id', $supplierId)
                  ->orWhere(function ($sub) use ($supplierId) {
                      $sub->whereNull('supplier_id')
                          ->whereExists(function ($exists) use ($supplierId) {
                              $exists->select(DB::raw(1))
                                  ->from('medicine_supplier')
                                  ->whereColumn('medicine_supplier.medicine_id', 'import_rows.medicine_id')
                                  ->where('medicine_supplier.supplier_id', $supplierId);
                          });
                  });
            })
            ->groupBy('product_code', 'product_name')
            ->orderBy('product_name')
            ->get([
                'product_code',
                DB::raw('MAX(product_name) AS product_name'),
                DB::raw('SUM(quantity) AS total_quantity'),
                DB::raw('COUNT(*) AS row_count'),
            ]);
    }

    /**
     * Mark all un-exported, exportable rows for the given supplier as exported
     * and attach them to the given ExportBatch.
     */
    public function markExported(int $supplierId, ExportBatch $batch): int
    {
        return DB::transaction(function () use ($supplierId, $batch) {
            return ImportRow::query()
                ->whereIn('status', self::EXPORTABLE_STATUSES)
                ->where('is_exported', false)
                ->where(function ($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId)
                      ->orWhere(function ($sub) use ($supplierId) {
                          $sub->whereNull('supplier_id')
                              ->whereExists(function ($exists) use ($supplierId) {
                                  $exists->select(DB::raw(1))
                                      ->from('medicine_supplier')
                                      ->whereColumn('medicine_supplier.medicine_id', 'import_rows.medicine_id')
                                      ->where('medicine_supplier.supplier_id', $supplierId);
                              });
                      });
                })
                ->update([
                    'is_exported'    => true,
                    'exported_at'    => now(),
                    'export_batch_id'=> $batch->id,
                ]);
        });
    }
}
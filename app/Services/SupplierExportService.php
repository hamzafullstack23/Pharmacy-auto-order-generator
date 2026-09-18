<?php

namespace App\Services;

use App\Models\ExportBatch;
use App\Models\ImportRow;
use App\Models\Medicine;
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
        $raw = ImportRow::query()
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
            ->groupBy('product_code')
            ->orderBy('product_code')
            ->get([
                'product_code',
                DB::raw('MAX(product_name) AS product_name'),
                DB::raw('SUM(quantity) AS total_units'),
                DB::raw('COUNT(*) AS row_count'),
            ]);

        // Fetch pack sizes for all involved product codes in one query
        $packSizes = Medicine::whereIn('product_code', $raw->pluck('product_code'))
            ->pluck('pack_size', 'product_code');

        return $raw->map(function ($r) use ($packSizes) {
            $packSize = (int) ($packSizes[$r->product_code] ?? 0);

            // If pack_size is missing or invalid, treat as 1 (loose)
            if ($packSize < 1) {
                $packSize = 1;
            }

            $totalUnits = (int) $r->total_units;
            $fullPacks  = intdiv($totalUnits, $packSize);
            $remainder  = $totalUnits % $packSize;

            return (object) [
                'product_code' => $r->product_code,
                'product_name' => $r->product_name,
                'total_units'  => $totalUnits,
                'pack_size'    => $packSize,
                'full_packs'   => $fullPacks,
                'remainder'    => $remainder,
                'row_count'    => (int) $r->row_count,
                'exportable'   => $fullPacks > 0,
            ];
        });
    }

    /**
     * Preview only — returns the products that are actually exportable
     * (i.e. have at least 1 full pack).
     */
    public function exportableRows(int $supplierId): Collection
    {
        return $this->aggregatedUnexportedRows($supplierId)
            ->filter(fn($r) => $r->exportable)
            ->values();
    }

    /**
     * All rows for the supplier, including those waiting for enough stock
     * to complete a pack. Use this to show the "pending" section in the UI.
     */
    public function pendingRows(int $supplierId): Collection
    {
        return $this->aggregatedUnexportedRows($supplierId)
            ->filter(fn($r) => !$r->exportable && $r->remainder > 0)
            ->values();
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
                    'export_batch_id' => $batch->id,
                ]);
        });
    }

    /**
     * Mark rows for a specific product as exported, consuming exactly
     * (packs * pack_size) units.
     *
     * Splits the last consumed row if it's partially used.
     */
    public function markProductExported(
        int $supplierId,
        ExportBatch $batch,
        string $productCode,
        int $packs,
        int $packSize
    ): int {
        $unitsToExport = $packs * $packSize;

        if ($unitsToExport <= 0) {
            return 0;
        }

        $marked = 0;

        DB::transaction(function () use (
            $supplierId,
            $batch,
            $productCode,
            $unitsToExport,
            &$marked
        ) {
            $rows = ImportRow::query()
                ->whereIn('status', self::EXPORTABLE_STATUSES)
                ->where('is_exported', false)
                ->where('product_code', $productCode)
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
                ->orderBy('sale_date')
                ->orderBy('id')
                ->get();

            $remaining = $unitsToExport;

            foreach ($rows as $row) {
                if ($remaining <= 0) break;

                $rowQty    = (int) $row->quantity;
                $consume   = min($remaining, $rowQty);

                if ($consume === $rowQty) {
                    // Full row consumed
                    $row->update([
                        'is_exported'     => true,
                        'exported_at'     => now(),
                        'export_batch_id' => $batch->id,
                    ]);
                    $marked += $rowQty;
                } else {
                    // Partial consumption — split the row
                    $remainderQty = $rowQty - $consume;

                    // Original row = exported portion
                    $row->update([
                        'quantity'        => $consume,
                        'is_exported'     => true,
                        'exported_at'     => now(),
                        'export_batch_id' => $batch->id,
                    ]);

                    // New row = remainder, still pending
                    ImportRow::create([
                        'import_batch_id' => $row->import_batch_id,
                        'row_number'      => $row->row_number,
                        'product_code'    => $row->product_code,
                        'product_name'    => $row->product_name,
                        'quantity'        => $remainderQty,
                        'sale_date'       => $row->sale_date,
                        'status'          => $row->status,
                        'medicine_id'     => $row->medicine_id,
                        'supplier_id'     => $row->supplier_id,
                        'company_id'      => $row->company_id,
                        'is_exported'     => false,
                    ]);

                    $marked += $consume;
                }

                $remaining -= $consume;
            }
        });

        return $marked;
    }
}

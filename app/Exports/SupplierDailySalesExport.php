<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;

class SupplierDailySalesExport implements FromArray
{
    public function __construct(
        protected string $supplierName,
        protected Collection $rows
    ) {}

    public function array(): array
    {
        $out = [];

        // Banner
        $out[] = ['=== MEDICA PLUS PHARMACY LMDC ==='];
        $out[] = ['Supplier: ' . $this->supplierName];
        $out[] = ['Exported on: ' . now()->format('Y-m-d H:i:s')];
        $out[] = [];

        // Column headers
        $out[] = ['Product Name', 'Quantity'];

        // Data
        foreach ($this->rows as $r) {
            $out[] = [
                $r->product_name,
                (float) $r->total_quantity,
            ];
        }

        // Total
        $out[] = ['TOTAL', (float) $this->rows->sum('total_quantity')];

        return $out;
    }
}
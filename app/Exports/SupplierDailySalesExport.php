<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SupplierDailySalesExport implements
    FromArray,
    WithColumnWidths,
    WithEvents,
    WithCustomCsvSettings
{
    protected string $supplierName;
    protected Collection $rows;

    // Layout constants — change here, applied everywhere
    protected int $titleRow    = 1;
    protected int $subtitleRow = 2;
    protected int $metaRow     = 3;
    protected int $spacer1     = 4;
    protected int $headerRow   = 5;
    protected int $firstDataRow = 6;

    public function __construct(string $supplierName, Collection $rows)
    {
        $this->supplierName = $supplierName;
        $this->rows         = $rows;
    }

    public function array(): array
    {
        $out = [];

        // Row 1: Company title
        $out[] = ['Medica Plus Pharmacy LMDC', '', '', ''];

        // Row 2: Supplier
        $out[] = ['Supplier: ' . $this->supplierName, '', '', ''];

        // Row 3: Exported on
        $out[] = ['Exported on: ' . now()->format('Y-m-d H:i:s'), '', '', ''];

        // Row 4: spacer
        $out[] = ['', '', '', ''];

        // Row 5: Header row
        $out[] = ['Product Name', 'Packs', 'Pack Size', 'Total Units'];

        // Row 6+: Data
        foreach ($this->rows as $r) {
            $out[] = [
                $r->product_name,
                (int) $r->full_packs,
                (int) $r->pack_size,
                (int) ($r->full_packs * $r->pack_size),
            ];
        }

        // Last row: TOTAL
        $out[] = [
            'TOTAL',
            (int) $this->rows->sum('full_packs'),
            '',
            (int) $this->rows->sum(fn($r) => $r->full_packs * $r->pack_size),
        ];

        return $out;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 55,
            'B' => 12,
            'C' => 12,
            'D' => 14,
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom'         => true,
            'output_encoding' => 'UTF-8',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastDataRow = $this->firstDataRow + $this->rows->count() - 1;
                $totalRow    = $lastDataRow + 1;

                /* ---------- TITLE (row 1) ---------- */
                $sheet->mergeCells("A{$this->titleRow}:D{$this->titleRow}");
                $sheet->getStyle("A{$this->titleRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 18,
                        'color' => ['rgb' => 'FFFFFF'],
                        'name'  => 'Calibri',
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F766E'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($this->titleRow)->setRowHeight(36);

                /* ---------- SUBTITLE (row 2) ---------- */
                $sheet->mergeCells("A{$this->subtitleRow}:D{$this->subtitleRow}");
                $sheet->getStyle("A{$this->subtitleRow}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size'   => 12,
                        'bold'   => true,
                        'color'  => ['rgb' => '0F766E'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CCFBF1'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($this->subtitleRow)->setRowHeight(24);

                /* ---------- META (row 3) ---------- */
                $sheet->mergeCells("A{$this->metaRow}:D{$this->metaRow}");
                $sheet->getStyle("A{$this->metaRow}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size'   => 9,
                        'color'  => ['rgb' => '64748B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($this->metaRow)->setRowHeight(16);

                /* ---------- SPACER (row 4) ---------- */
                $sheet->getRowDimension($this->spacer1)->setRowHeight(8);

                /* ---------- HEADER (row 5) ---------- */
                $headerRange = "A{$this->headerRow}:D{$this->headerRow}";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 11,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E293B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '334155'],
                        ],
                    ],
                ]);

                // Right-align the numeric headers (B, C, D)
                foreach (['B', 'C', 'D'] as $col) {
                    $sheet->getStyle("{$col}{$this->headerRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getRowDimension($this->headerRow)->setRowHeight(26);

                /* ---------- DATA ROWS ---------- */
                $dataRange = "A{$this->firstDataRow}:D{$lastDataRow}";
                $sheet->getStyle($dataRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                    'font' => [
                        'size' => 10,
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                // Alternating rows
                for ($i = $this->firstDataRow; $i <= $lastDataRow; $i++) {
                    if (($i - $this->firstDataRow) % 2 === 1) {
                        $sheet->getStyle("A{$i}:D{$i}")->applyFromArray([
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F1F5F9'],
                            ],
                        ]);
                    }
                    $sheet->getRowDimension($i)->setRowHeight(20);
                }

                // Center the pack columns
                $sheet->getStyle("B{$this->firstDataRow}:D{$lastDataRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                /* ---------- TOTAL ROW ---------- */
                $sheet->getStyle("A{$totalRow}:D{$totalRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 11,
                        'color' => ['rgb' => '0F172A'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2E8F0'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color'       => ['rgb' => '0F172A'],
                        ],
                        'bottom' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color'       => ['rgb' => '0F172A'],
                        ],
                        'left' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CBD5E1'],
                        ],
                        'right' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);

                $sheet->getStyle("B{$totalRow}:D{$totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getRowDimension($totalRow)->setRowHeight(26);

                /* ---------- MISC ---------- */
                $sheet->freezePane("A" . ($this->firstDataRow));
                $sheet->setShowGridlines(false);
            },
        ];
    }
}
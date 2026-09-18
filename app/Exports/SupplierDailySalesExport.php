<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SupplierDailySalesExport implements
    FromView,
    ShouldAutoSize,
    WithCustomCsvSettings,
    WithColumnWidths,
    WithEvents
{
    protected string $supplierName;
    protected Collection $rows;

    public function __construct(string $supplierName, Collection $rows)
    {
        $this->supplierName = $supplierName;
        $this->rows         = $rows;
    }

    public function view(): View
    {
        return view('exports.supplier-daily-sales', [
            'supplierName' => $this->supplierName,
            'rows'         => $this->rows,
        ]);
    }

    /**
     * CSV fallback (kept so this class can also drive a CSV download).
     */
    public function getCsvSettings(): array
    {
        return [
            'use_bom'         => true,
            'output_encoding' => 'UTF-8',
        ];
    }

    /**
     * Excel column widths (used when auto-size doesn't apply, e.g. for the index column).
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,    // #
            'B' => 55,   // Product Name
            'C' => 14,   // Quantity
        ];
    }

    /**
     * Advanced styling applied after the sheet is built.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastDataRow = 7 + $this->rows->count();   // see template layout
                $totalRow    = $lastDataRow + 1;

                /* ---------- HEADER BLOCK ---------- */

                // Title: rows 1-2, cells A1:C2 merged
                $sheet->mergeCells('A1:C1');
                $sheet->mergeCells('A2:C2');
                $sheet->mergeCells('A3:C3');

                // Company name (row 1)
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 18,
                        'color' => ['rgb' => 'FFFFFF'],
                        'name'  => 'Calibri',
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F766E'],    // teal-700
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(34);

                // Subtitle (row 2)
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size'   => 11,
                        'color'  => ['rgb' => '0F766E'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CCFBF1'],    // teal-100
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(22);

                // Small spacer (row 3)
                $sheet->getRowDimension(3)->setRowHeight(8);

                /* ---------- META BLOCK (rows 4-6) ---------- */

                // Build the meta rows manually as text: they're already in the Blade view
                // Style them for readability
                foreach ([4, 5, 6] as $r) {
                    $sheet->getStyle("A{$r}:C{$r}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8FAFC'],   // slate-50
                        ],
                        'font' => [
                            'size' => 10,
                            'color'=> ['rgb' => '334155'],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }
                $sheet->getStyle('A4:A6')->applyFromArray([
                    'font' => ['bold' => true],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(18);
                $sheet->getRowDimension(5)->setRowHeight(18);
                $sheet->getRowDimension(6)->setRowHeight(18);

                // Row 7 spacer
                $sheet->getRowDimension(7)->setRowHeight(6);

                /* ---------- TABLE HEADER (row 8) ---------- */

                $headerRow = 8;
                $sheet->getStyle("A{$headerRow}:C{$headerRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 11,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E293B'],   // slate-800
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
                // Quantity column header aligned right
                $sheet->getStyle("C{$headerRow}")
                      ->getAlignment()
                      ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getRowDimension($headerRow)->setRowHeight(26);

                /* ---------- DATA ROWS ---------- */

                $firstDataRow = $headerRow + 1;
                $lastDataRow  = $headerRow + $this->rows->count();

                // Apply borders to all data cells
                $sheet->getStyle("A{$firstDataRow}:C{$lastDataRow}")->applyFromArray([
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
                    ],
                ]);

                // Alternating row shading (zebra stripes)
                for ($i = $firstDataRow; $i <= $lastDataRow; $i++) {
                    if (($i - $firstDataRow) % 2 === 1) {
                        $sheet->getStyle("A{$i}:C{$i}")->applyFromArray([
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F1F5F9'],   // slate-100
                            ],
                        ]);
                    }
                    $sheet->getRowDimension($i)->setRowHeight(20);
                }

                // Right-align quantity column
                $sheet->getStyle("C{$firstDataRow}:C{$lastDataRow}")
                      ->getAlignment()
                      ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Center the index column
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")
                      ->getAlignment()
                      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                /* ---------- TOTAL ROW ---------- */

                $totalRow = $lastDataRow + 1;

                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                $sheet->getStyle("A{$totalRow}:C{$totalRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 11,
                        'color' => ['rgb' => '0F172A'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2E8F0'],   // slate-200
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical'   => Alignment::VERTICAL_CENTER,
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

                $sheet->getStyle("A{$totalRow}")
                      ->getAlignment()
                      ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getRowDimension($totalRow)->setRowHeight(26);

                /* ---------- FOOTER ---------- */

                $footerRow = $totalRow + 2;
                $sheet->mergeCells("A{$footerRow}:C{$footerRow}");

                $sheet->getStyle("A{$footerRow}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size'   => 9,
                        'color'  => ['rgb' => '94A3B8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                /* ---------- MISC ---------- */

                // Freeze below the header row (row 8) so scrolling keeps it visible
                $sheet->freezePane('A9');

                // Hide gridlines for a cleaner look
                $sheet->setShowGridlines(false);

                // Page setup for printing
                $sheet->getPageSetup()
                      ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
                      ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

                $sheet->getPageMargins()
                      ->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
            },
        ];
    }
}
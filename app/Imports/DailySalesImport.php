<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

class DailySalesImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithValidation,
    SkipsEmptyRows,
    SkipsOnFailure,
    SkipsOnError,
    WithCustomCsvSettings
{
    use Importable;

    protected string $saleDate;
    protected ImportBatch $batch;

    protected array $errors = [];
    protected int $stagedCount = 0;
    protected int $skippedCount = 0;
    protected int $duplicateCount = 0;
    protected int $excludedCount = 0;
    protected int $rowCursor = 0;

    /** Cached exclusion list (lowercased). */
    protected array $excludedCodes = [];

    public function __construct(string $saleDate, ImportBatch $batch)
    {
        $this->saleDate = Carbon::parse($saleDate)->format('Y-m-d');
        $this->batch    = $batch;

        // Load exclusions once, normalize to lowercase for case-insensitive matching
        $raw = (array) config('import_exclusions.product_codes', []);
        $this->excludedCodes = array_map(
            fn($c) => strtolower(trim((string) $c)),
            $raw
        );
    }

    /* -------------------------------------------------------------------
     |  CSV reading settings (encoding fix)
     * ------------------------------------------------------------------ */
    public function getCsvSettings(): array
    {
        return [
            'input_encoding' => 'Windows-1252',
        ];
    }

    /* -------------------------------------------------------------------
     |  Validation
     * ------------------------------------------------------------------ */
    public function prepareForValidation($row): array
    {
        $prepared = [];
        foreach ($row as $key => $value) {
            $prepared[$this->normalizeColumnName($key)] = $value;
        }

        foreach (['product_code', 'product_name'] as $field) {
            if (empty($prepared[$field])) {
                foreach ($row as $key => $value) {
                    if (stripos($key, str_replace('_', ' ', $field)) !== false && !empty($value)) {
                        $prepared[$field] = (string) $value;
                        break;
                    }
                }
            }
        }

        if (isset($prepared['product_code']) && $prepared['product_code'] !== '') {
            $prepared['product_code'] = (string) $prepared['product_code'];
        }

        return $prepared;
    }

    protected function normalizeColumnName(string $column): string
    {
        $column = preg_replace('/^\xEF\xBB\xBF/', '', $column);
        $column = preg_replace('/^\x{FEFF}/u', '', $column);
        $column = trim($column);

        $exactMatches = [
            'Product Code' => 'product_code',
            'ProductCode'  => 'product_code',
            'Product Name' => 'product_name',
            'ProductName'  => 'product_name',
            'Quantity'     => 'quantity',
            'Date'         => 'date',
            'Sale Date'    => 'sale_date',
            'Sales Date'   => 'sale_date',
        ];

        if (isset($exactMatches[$column])) {
            return $exactMatches[$column];
        }

        $column = strtolower($column);
        $column = preg_replace('/[^a-z0-9_]/', '_', $column);
        $column = preg_replace('/_+/', '_', $column);

        return trim($column, '_');
    }

    public function rules(): array
    {
        return [
            'product_code' => 'required|string',
            'product_name' => 'required|string',
            'quantity'     => 'required|numeric|min:0.01',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'product_code.required' => 'Product Code is required.',
            'product_name.required' => 'Product Name is required.',
            'quantity.required'     => 'Quantity is required.',
            'quantity.numeric'      => 'Quantity must be a number.',
            'quantity.min'          => 'Quantity must be greater than 0.',
        ];
    }

    /* -------------------------------------------------------------------
     |  Staging loop — this is where duplicate & exclusion checks happen
     * ------------------------------------------------------------------ */
    public function collection(Collection $rows): void
    {
        $inserts = [];
        $now     = now();

        foreach ($rows as $row) {
            $this->rowCursor++;
            $row = $row->toArray();

            $productCode = trim((string) ($row['product_code'] ?? ''));
            $productName = trim((string) ($row['product_name'] ?? ''));
            $quantity    = (float) ($row['quantity'] ?? 0);
            $rawDate     = $row['date'] ?? $row['sale_date'] ?? null;
            $saleDate    = !empty($rawDate) ? $this->parseDate($rawDate) : $this->saleDate;

            // Basic validation guard (in case validation was skipped)
            if (empty($productCode) || empty($productName) || $quantity <= 0) {
                $this->skippedCount++;
                $this->errors[] = "Row {$this->rowCursor}: Missing/invalid data.";
                continue;
            }

            // ---------- Exclusion check ----------
            if (in_array(strtolower($productCode), $this->excludedCodes, true)) {
                $this->excludedCount++;
                continue;
            }

            // ---------- Duplicate check (per row: date + code + quantity) ----------
            if ($this->rowAlreadyExists($saleDate, $productCode, $quantity)) {
                $this->duplicateCount++;
                continue;
            }

            $inserts[] = [
                'import_batch_id' => $this->batch->id,
                'row_number'      => $this->rowCursor,
                'product_code'    => $productCode,
                'product_name'    => $productName,
                'quantity'        => $quantity,
                'sale_date'       => $saleDate,
                'status'          => 'pending',
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        if (!empty($inserts)) {
            ImportRow::insert($inserts);
            $this->stagedCount += count($inserts);
        }
    }

    /**
     * Return true when an identical row already exists in import_rows.
     *
     * Option B (default): matches on sale_date + product_code + quantity.
     * Option A: switch the body below to only check sale_date.
     */
    protected function rowAlreadyExists(string $saleDate, string $productCode, float $quantity): bool
    {
        return ImportRow::where('sale_date', $saleDate)
            ->where('product_code', $productCode)
            ->where('quantity', $quantity)
            ->exists();

        // ----- Option A alternative (date-only match) -----
        // return ImportRow::where('sale_date', $saleDate)->exists();
    }

    /* -------------------------------------------------------------------
     |  Date parsing
     * ------------------------------------------------------------------ */
    protected function parseDate(?string $dateString): string
    {
        if (empty($dateString)) {
            return $this->saleDate;
        }

        $dateString = trim($dateString);

        $formats = [
            'm/d/Y', 'd/m/Y', 'Y-m-d',
            'm/d/Y H:i:s', 'd/m/Y H:i:s', 'Y-m-d H:i:s',
            'M d, Y', 'd M Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return $this->saleDate;
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    /* -------------------------------------------------------------------
     |  Failure / error handlers
     * ------------------------------------------------------------------ */
    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->errors[] = "Row {$failure->row()} skipped: " . implode(', ', $failure->errors());
            $this->skippedCount++;
        }
    }

    public function onError(Throwable $e): void
    {
        $this->errors[] = 'Row error: ' . $e->getMessage();
        $this->skippedCount++;
    }

    /* -------------------------------------------------------------------
     |  Getters (used by the controller)
     * ------------------------------------------------------------------ */
    public function getStagedCount(): int    { return $this->stagedCount; }
    public function getSkippedCount(): int   { return $this->skippedCount; }
    public function getDuplicateCount(): int { return $this->duplicateCount; }
    public function getExcludedCount(): int  { return $this->excludedCount; }
    public function getErrors(): array        { return $this->errors; }
}
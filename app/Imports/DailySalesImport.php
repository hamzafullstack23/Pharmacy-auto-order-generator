<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Medicine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;
use App\Support\StringSanitizer;

class DailySalesImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithValidation,
    SkipsEmptyRows,
    SkipsOnFailure,
    SkipsOnError
{
    use Importable;

    protected string $saleDate;
    protected ImportBatch $batch;
    protected array $errors = [];
    protected int $stagedCount = 0;
    protected int $skippedCount = 0;
    protected int $rowCursor = 0;

    public function __construct(string $saleDate, ImportBatch $batch)
    {
        $this->saleDate = Carbon::parse($saleDate)->format('Y-m-d');
        $this->batch = $batch;
    }

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

    public function collection(Collection $rows): void
    {
        $inserts = [];
        $now = now();

        foreach ($rows as $row) {
            $this->rowCursor++;
            $row = $row->toArray();

            $productCode = trim((string) ($row['product_code'] ?? ''));
            $productName = trim((string) ($row['product_name'] ?? ''));
            $quantity    = (float) ($row['quantity'] ?? 0);
            $rawDate     = $row['date'] ?? $row['sale_date'] ?? null;
            $saleDate    = !empty($rawDate) ? $this->parseDate($rawDate) : $this->saleDate;

            if (empty($productCode) || empty($productName) || $quantity <= 0) {
                $this->skippedCount++;
                $this->errors[] = "Row {$this->rowCursor}: Missing/invalid data.";
                continue;
            }

            $inserts[] = [
                'import_batch_id' => $this->batch->id,
                'row_number'      => $this->rowCursor,
                'product_code'    => $productCode,
                'product_name'    => StringSanitizer::cleanName($productName ?? null),
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

    public function getStagedCount(): int
    {
        return $this->stagedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
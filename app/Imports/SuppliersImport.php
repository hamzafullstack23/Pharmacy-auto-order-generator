<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Validation\Rule;

class SuppliersImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    SkipsEmptyRows,
    WithChunkReading,
    WithBatchInserts
{
    protected $importedCount = 0;
    protected $errors = [];
    protected $stats = [
        'created' => 0,
        'skipped' => 0,
    ];

    public function model(array $row)
    {
        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            $this->stats['skipped']++;
            $this->errors[] = 'Row skipped: missing supplier name';
            return null;
        }

        // Case-insensitive, whitespace-insensitive duplicate check
        $existing = Supplier::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first();

        if ($existing) {
            $this->stats['skipped']++;
            $this->errors[] = "Row skipped: Supplier '{$name}' already exists";
            return null;
        }

        $orderDay = strtolower(trim((string) ($row['order_day'] ?? ''))) ?: 'monday';

        $supplier = Supplier::create([
            'name' => $name,
            'contact_person' => $this->cleanString($row['contact_person'] ?? null),
            'email' => $this->cleanString($row['email'] ?? null),
            'phone' => $this->cleanString($row['phone'] ?? null),
            'address' => $this->cleanString($row['address'] ?? null),
            'order_day' => $orderDay,
        ]);

        $this->importedCount++;
        $this->stats['created']++;

        return $supplier;
    }

    /**
     * Trim strings and convert empty strings to null so blank Excel cells
     * don't get stored as "" instead of NULL.
     */
    protected function cleanString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            '*.name' => 'required|string|max:255',
            '*.contact_person' => 'nullable|string|max:255',
            '*.email' => 'nullable|email:rfc|max:255',
            '*.phone' => 'nullable|string|max:50',
            '*.address' => 'nullable|string|max:1000',
            '*.order_day' => ['nullable', 'string', Rule::in([
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            ])],
        ];
    }

    /**
     * Normalize values before the validator sees them, so e.g. "Monday"
     * (capitalized) or " john@doe.com " (padded) don't fail validation
     * unnecessarily.
     */
    public function prepareForValidation($row, $index)
    {
        if (isset($row['order_day'])) {
            $row['order_day'] = strtolower(trim($row['order_day']));
        }

        if (isset($row['email'])) {
            $row['email'] = trim($row['email']);
        }

        if (isset($row['name'])) {
            $row['name'] = trim($row['name']);
        }

        return $row;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->stats['skipped']++;
            $this->errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
        }
    }

    /**
     * Read the file in chunks so large uploads (up to 100MB per the
     * upload form) don't exhaust memory or time out.
     */
    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getStats()
    {
        return $this->stats;
    }
}
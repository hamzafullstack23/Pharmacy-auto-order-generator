<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\Company;
use App\Models\Medicine;
use App\Models\MedicineSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Support\StringSanitizer;

class ProductImportService
{
    protected $stats = [
        'total_rows' => 0,
        'processed_rows' => 0,
        'suppliers_created' => 0,
        'suppliers_updated' => 0,
        'companies_created' => 0,
        'companies_updated' => 0,
        'medicines_created' => 0,
        'medicines_updated' => 0,
        'medicine_supplier_created' => 0,
        'errors' => [],
        'warnings' => [],
        'duplicates_skipped' => 0,
        'skipped_rows' => 0,
        'progress' => 0,
        'current_chunk' => 0,
        'total_chunks' => 0,
    ];

    protected $supplierCache = [];
    protected $companyCache = [];
    protected $medicineCache = [];
    protected $progressCallback = null;

    // Fixed column indexes based on your CSV structure
    protected $columnIndexes = [
        'product_code' => 0,
        'product_name' => 1,
        'category_name' => 2,
        'manufacturer' => 4,
        'supplier_name' => 18,
        'cost' => 11,
        'retail_price' => 12,
        'unit' => 25,
    ];

    public function onProgress(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    public function import(string $filePath, bool $dryRun = false, int $chunkSize = 100): array
    {
        $this->resetStats();

        try {
            // Check if product_code column exists
            if (!Schema::hasColumn('medicines', 'product_code')) {
                $this->stats['errors'][] = 'Product code column does not exist. Run: php artisan migrate';
                return $this->stats;
            }

            // Verify file exists and can be read
            if (!file_exists($filePath)) {
                $this->stats['errors'][] = "File not found: {$filePath}";
                return $this->stats;
            }

            // Count total rows
            $totalRows = $this->countCsvRows($filePath);
            $this->stats['total_rows'] = $totalRows;

            if ($totalRows === 0) {
                $this->stats['errors'][] = 'CSV file is empty';
                return $this->stats;
            }

            $totalChunks = ceil($totalRows / $chunkSize);
            $this->stats['total_chunks'] = $totalChunks;

            if ($dryRun) {
                $this->processDryRun($filePath);
                $this->stats['warnings'][] = 'Dry run completed. No changes were made.';
                return $this->stats;
            }

            // Pre-load all existing data
            $this->loadExistingData();

            DB::beginTransaction();

            try {
                for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
                    $this->stats['current_chunk'] = $chunk + 1;
                    
                    $rows = $this->readCsvChunk($filePath, $chunk, $chunkSize);
                    
                    if (empty($rows)) {
                        continue;
                    }

                    $sanitizedRows = $this->sanitizeRows($rows);
                    
                    $validRows = [];
                    $rowNumber = ($chunk * $chunkSize) + 1;
                    foreach ($sanitizedRows as $index => $row) {
                        $currentRowNumber = $rowNumber + $index;
                        
                        // Check if required fields are empty
                        $missing = [];
                        if (empty($row['product_name'])) $missing[] = 'product_name';
                        if (empty($row['manufacturer'])) $missing[] = 'manufacturer';
                        if (empty($row['supplier_name'])) $missing[] = 'supplier_name';
                        
                        if (!empty($missing)) {
                            $this->stats['warnings'][] = "Row {$currentRowNumber}: Skipped - missing: " . implode(', ', $missing);
                            $this->stats['skipped_rows']++;
                            continue;
                        }
                        
                        $validRows[] = $row;
                    }
                    
                    if (empty($validRows)) {
                        continue;
                    }

                    $processedData = $this->prepareData($validRows);
                    
                    // Process using cached data
                    $this->processChunkWithCache($processedData);
                    
                    $this->stats['processed_rows'] += count($rows);
                    $this->stats['progress'] = min(100, round(($this->stats['processed_rows'] / $totalRows) * 100));
                    
                    if ($this->progressCallback) {
                        call_user_func($this->progressCallback, $this->stats);
                    }

                    if ($chunk % 5 === 0) {
                        gc_collect_cycles();
                    }
                }

                DB::commit();
                $this->stats['warnings'][] = 'Import completed successfully!';
                
            } catch (\Exception $e) {
                DB::rollBack();
                $this->stats['errors'][] = 'Database transaction failed: ' . $e->getMessage();
                Log::error('Import transaction failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }

        } catch (\Exception $e) {
            $this->stats['errors'][] = 'Import failed: ' . $e->getMessage();
            Log::error('Import failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return $this->stats;
    }

    protected function readCsvChunk(string $filePath, int $chunkIndex, int $chunkSize): array
{
    $rows = [];
    $handle = fopen($filePath, 'r');
    
    if ($handle === false) {
        return [];
    }

    // Skip header
    fgetcsv($handle, 0, ',', '"', '\\');
    
    // Skip to chunk start
    $startRow = $chunkIndex * $chunkSize;
    $currentRow = 0;
    
    while ($currentRow < $startRow && ($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $currentRow++;
    }
    
    // Read chunk
    $rowCount = 0;
    while ($rowCount < $chunkSize && ($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        // Skip completely empty rows
        if (empty(array_filter($row))) {
            // Count this as processed but don't add to rows
            $rowCount++;
            continue;
        }
        
        // Map using fixed indexes
        $rowData = [];
        $hasData = false;
        foreach ($this->columnIndexes as $key => $index) {
            $value = isset($row[$index]) ? trim($row[$index]) : null;
            if ($value === '' || $value === 'NULL' || $value === 'null') {
                $value = null;
            }
            $rowData[$key] = $value;
            if ($value !== null && $value !== '') {
                $hasData = true;
            }
        }
        
        // Only add rows that have at least some data
        if ($hasData) {
            $rows[] = $rowData;
        }
        $rowCount++;
    }
    
    fclose($handle);
    return $rows;
}

    /**
     * Count total rows in CSV (excluding header)
     */
    protected function countCsvRows(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            return 0;
        }

        fgetcsv($handle, 0, ',', '"', '\\');
        
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (!empty(array_filter($row))) {
                $count++;
            }
        }
        
        fclose($handle);
        return $count;
    }

    /**
     * Process dry run
     */
    protected function processDryRun(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return;
        }

        fgetcsv($handle, 0, ',', '"', '\\');
        
        $rowNumber = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }
            
            $rowNumber++;
            
            $rowData = [];
            foreach ($this->columnIndexes as $key => $index) {
                $value = isset($row[$index]) ? trim($row[$index]) : null;
                if ($value === '' || $value === 'NULL' || $value === 'null') {
                    $value = null;
                }
                $rowData[$key] = $value;
            }
            
            if (empty($rowData['product_name']) || empty($rowData['manufacturer']) || empty($rowData['supplier_name'])) {
                $missing = [];
                if (empty($rowData['product_name'])) $missing[] = 'product_name';
                if (empty($rowData['manufacturer'])) $missing[] = 'manufacturer';
                if (empty($rowData['supplier_name'])) $missing[] = 'supplier_name';
                
                $this->stats['warnings'][] = "Row {$rowNumber}: Missing: " . implode(', ', $missing);
                $this->stats['skipped_rows']++;
                continue;
            }
            
            $this->stats['processed_rows']++;
            
            if ($this->stats['total_rows'] > 0) {
                $this->stats['progress'] = min(100, round(($this->stats['processed_rows'] / $this->stats['total_rows']) * 100));
            }
            
            if ($this->progressCallback) {
                call_user_func($this->progressCallback, $this->stats);
            }
        }
        
        fclose($handle);
    }

    /**
     * Pre-load all existing companies and suppliers
     */
    protected function loadExistingData(): void
    {
        // Load all suppliers
        $suppliers = Supplier::all();
        foreach ($suppliers as $supplier) {
            $this->supplierCache[trim($supplier->name)] = $supplier->id;
        }
        Log::info("Loaded " . count($this->supplierCache) . " suppliers into cache");

        // Load all companies
        $companies = Company::all();
        foreach ($companies as $company) {
            $key = trim($company->name) . '|' . $company->supplier_id;
            $this->companyCache[$key] = $company->id;
            $this->companyCache[trim($company->name)] = $company->id;
        }
        Log::info("Loaded " . count($this->companyCache) . " companies into cache");
    }

    /**
     * Process chunk using cached data
     */
    protected function processChunkWithCache(array $data): void
    {
        $supplierMap = $this->processSuppliersWithCache($data['suppliers']);
        $companyMap = $this->processCompaniesWithCache($data['companies'], $supplierMap);
        $this->processMedicinesWithCache($data['medicines'], $companyMap, $supplierMap);
    }

    /**
     * Process suppliers using cache
     */
    protected function processSuppliersWithCache(array $suppliers): array
    {
        $supplierMap = [];

        foreach ($suppliers as $name => $data) {
            if (empty($name)) {
                continue;
            }

            $name = trim($name);

            if (isset($this->supplierCache[$name])) {
                $supplierMap[$name] = $this->supplierCache[$name];
                $this->stats['suppliers_updated']++;
                continue;
            }

            $existing = Supplier::where(DB::raw('LOWER(name)'), '=', strtolower($name))->first();
            if ($existing) {
                $this->supplierCache[$name] = $existing->id;
                $supplierMap[$name] = $existing->id;
                $this->stats['suppliers_updated']++;
                continue;
            }

            try {
                $supplier = Supplier::create([
                    'name' => $name,
                    'contact_person' => $data['contact_person'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'order_day' => $data['order_day'] ?? 'monday',
                    'is_active' => $data['is_active'] ?? true,
                ]);

                $this->supplierCache[$name] = $supplier->id;
                $supplierMap[$name] = $supplier->id;
                $this->stats['suppliers_created']++;
                Log::info("Created supplier: {$name}");
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Failed to create supplier '{$name}': " . $e->getMessage();
            }
        }

        return $supplierMap;
    }

    /**
     * Process companies using cache
     */
    protected function processCompaniesWithCache(array $companies, array $supplierMap): array
    {
        $companyMap = [];

        foreach ($companies as $key => $data) {
            $supplierId = $supplierMap[trim($data['supplier_name'])] ?? null;
            
            if (!$supplierId) {
                $this->stats['warnings'][] = "Skipping company '{$data['name']}' - Supplier not found";
                continue;
            }

            $companyName = trim($data['name']);
            $cacheKey = $companyName . '|' . $supplierId;

            if (isset($this->companyCache[$cacheKey])) {
                $companyMap[$key] = $this->companyCache[$cacheKey];
                $this->stats['companies_updated']++;
                continue;
            }

            $existing = Company::where(DB::raw('LOWER(name)'), '=', strtolower($companyName))
                               ->where('supplier_id', $supplierId)
                               ->first();
            
            if ($existing) {
                $this->companyCache[$cacheKey] = $existing->id;
                $companyMap[$key] = $existing->id;
                $this->stats['companies_updated']++;
                continue;
            }

            try {
                $company = Company::create([
                    'name' => $companyName,
                    'supplier_id' => $supplierId,
                    'contact_person' => $data['contact_person'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ]);

                $this->companyCache[$cacheKey] = $company->id;
                $companyMap[$key] = $company->id;
                $this->stats['companies_created']++;
                Log::info("Created company: {$companyName}");
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $existing = Company::where(DB::raw('LOWER(name)'), '=', strtolower($companyName))->first();
                    if ($existing) {
                        $this->companyCache[$cacheKey] = $existing->id;
                        $companyMap[$key] = $existing->id;
                        $this->stats['companies_updated']++;
                        continue;
                    }
                }
                $this->stats['errors'][] = "Failed to create company '{$companyName}': " . $e->getMessage();
            }
        }

        return $companyMap;
    }

    /**
     * Process medicines using cache
     */
    protected function processMedicinesWithCache(array $medicines, array $companyMap, array $supplierMap): void
    {
        foreach ($medicines as $key => $data) {
            if (empty($data['name']) || empty($data['company_name'])) {
                $this->stats['warnings'][] = "Skipping medicine with missing name or company";
                $this->stats['skipped_rows']++;
                continue;
            }

            $companyKey = trim($data['company_name']) . '|' . trim($data['supplier_name'] ?? '');
            $companyId = $companyMap[$companyKey] ?? null;
            $supplierId = $supplierMap[trim($data['supplier_name'] ?? '')] ?? null;

            if (!$companyId) {
                $this->stats['warnings'][] = "Skipping '{$data['name']}' - Company not found: {$data['company_name']}";
                $this->stats['skipped_rows']++;
                continue;
            }

            $medicineName = trim($data['name']);

            // Check if medicine exists by product_code
            if (!empty($data['product_code'])) {
                $existing = Medicine::where('product_code', $data['product_code'])->first();
                if ($existing) {
                    $this->stats['duplicates_skipped']++;
                    continue;
                }
            }

            // Check if medicine exists by name + company
            $cacheKey = $medicineName . '|' . $companyId;
            if (isset($this->medicineCache[$cacheKey])) {
                $existing = Medicine::find($this->medicineCache[$cacheKey]);
            } else {
                $existing = Medicine::where(DB::raw('LOWER(name)'), '=', strtolower($medicineName))
                                    ->where('company_id', $companyId)
                                    ->first();
            }

            try {
                if ($existing) {
                    $updateData = [];
                    if (!empty($data['unit'])) $updateData['unit'] = $data['unit'];
                    if ($data['cost'] !== null && $data['cost'] >= 0) $updateData['cost'] = $data['cost'];
                    if (!empty($data['pack_size'])) $updateData['pack_size'] = $data['pack_size'];
                    
                    if (!empty($updateData)) {
                        $existing->update($updateData);
                        $this->stats['medicines_updated']++;
                    }
                    
                    $medicineId = $existing->id;
                    $this->medicineCache[$cacheKey] = $medicineId;
                } else {
                    // Create new medicine
                    $packType = 'loose';
                    $packSize = null;
                    $unit = $data['unit'] ?? null;

                    if ($unit && preg_match('/(\d+)/', $unit, $matches)) {
                        $packType = 'pack';
                        $packSize = (int) $matches[1];
                    }

                    $medicineData = [
                        'product_code' => $data['product_code'] ?? null,
                        'name' => $medicineName,
                        'company_id' => $companyId,
                        'unit' => $unit,
                        'cost' => ($data['cost'] !== null && $data['cost'] >= 0) ? $data['cost'] : null,
                        'pack_type' => $packType,
                        'pack_size' => $packSize ?? null,
                        'max_stock_limit' => null,
                        'current_stock' => 0,
                        'is_active' => true,
                    ];

                    $medicine = Medicine::create($medicineData);
                    $medicineId = $medicine->id;
                    $this->medicineCache[$cacheKey] = $medicineId;
                    $this->stats['medicines_created']++;
                    Log::info("Created medicine: {$medicineName}");
                }

                // Create medicine_supplier relationship
                if ($medicineId && $supplierId) {
                    $this->createMedicineSupplier($medicineId, $supplierId, $data);
                }
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Failed to process '{$medicineName}': " . $e->getMessage();
                Log::error("Failed to process '{$medicineName}': " . $e->getMessage());
            }
        }
    }

    /**
     * Sanitize numeric fields
     */
    protected function sanitizeNumeric($value)
    {
        if ($value === null || $value === '' || $value === 'NULL' || $value === 'null') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
        
        if ($cleaned === '' || $cleaned === '-') {
            return null;
        }

        $numeric = (float) $cleaned;
        return $numeric >= 0 ? $numeric : null;
    }

    /**
     * Sanitize string
     */
    protected function sanitizeString($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $string = trim((string) $value);

    // Fix encoding FIRST, before any /u (unicode-mode) regex touches the string
    if (!mb_check_encoding($string, 'UTF-8')) {
        // Your export is Windows-1252 (confirmed via `file` on the CSV).
        // Be explicit rather than 'auto' - 'auto' guesses and can get it wrong.
        $string = mb_convert_encoding($string, 'UTF-8', 'Windows-1252');
    }

    // Now it's safe to run /u-mode regexes
    $string = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $string);
    $string = preg_replace('/[\x{FFFD}\x{FFFE}\x{FFFF}]/u', '', $string);
    $string = str_replace(['�', '�', '�', '�', '�', '�', '�', '�', '�', '�'], '', $string);

    return $string;
}

    /**
     * Sanitize all rows
     */
    protected function sanitizeRows(array $rows): array
    {
        $sanitized = [];
        
        foreach ($rows as $row) {
            $sanitizedRow = [];
            foreach ($row as $key => $value) {
                if ($value === null || $value === '' || $value === 'NULL' || $value === 'null') {
                    $sanitizedRow[$key] = null;
                    continue;
                }

                if (in_array($key, ['cost', 'retail_price', 'pack_size'])) {
                    $sanitizedRow[$key] = $this->sanitizeNumeric($value);
                } else {
                    $sanitizedRow[$key] = $this->sanitizeString($value);
                }
            }
            $sanitized[] = $sanitizedRow;
        }
        
        return $sanitized;
    }

    /**
     * Prepare data for processing
     */
    protected function prepareData(array $rows): array
    {
        $data = [
            'suppliers' => [],
            'companies' => [],
            'medicines' => [],
        ];

        foreach ($rows as $row) {
            if (!empty($row['supplier_name'])) {
                $data['suppliers'][trim($row['supplier_name'])] = [
                    'name' => trim($row['supplier_name']),
                    'contact_person' => null,
                    'email' => null,
                    'phone' => null,
                    'address' => null,
                    'order_day' => 'monday',
                    'is_active' => true,
                ];
            }

            if (!empty($row['manufacturer']) && !empty($row['supplier_name'])) {
                $companyKey = trim($row['manufacturer']) . '|' . trim($row['supplier_name']);
                $data['companies'][$companyKey] = [
                    'name' => trim($row['manufacturer']),
                    'supplier_name' => trim($row['supplier_name']),
                    'contact_person' => null,
                    'email' => null,
                    'phone' => null,
                    'address' => null,
                    'is_active' => true,
                ];
            }

            if (!empty($row['product_name']) && !empty($row['manufacturer'])) {
                $medicineKey = !empty($row['product_code']) 
                    ? trim($row['product_code']) 
                    : trim($row['product_name']) . '|' . trim($row['manufacturer']);
                
                $data['medicines'][$medicineKey] = [
                    'product_code' => $row['product_code'] ?? null,
                    'name' => trim(StringSanitizer::cleanName($row['product_name'] ?? null)),
                    'company_name' => trim($row['manufacturer']),
                    'supplier_name' => trim($row['supplier_name'] ?? ''),
                    'category_name' => $row['category_name'] ?? null,
                    'generics' => $row['generics'] ?? null,
                    'unit' => $row['unit'] ?? null,
                    'pack_size' => $row['pack_size'] ?? null,
                    'cost' => $row['cost'] ?? null,
                    'retail_price' => $row['retail_price'] ?? null,
                ];
            }
        }

        return $data;
    }

    /**
     * Create medicine_supplier relationship
     */
    protected function createMedicineSupplier(int $medicineId, int $supplierId, array $data): void
    {
        try {
            if (!Schema::hasTable('medicine_supplier')) {
                return;
            }
            
            $existing = MedicineSupplier::where('medicine_id', $medicineId)
                                        ->where('supplier_id', $supplierId)
                                        ->first();

            if ($existing) {
                return;
            }

            MedicineSupplier::create([
                'medicine_id' => $medicineId,
                'supplier_id' => $supplierId,
                'supplier_medicine_code' => $data['product_code'] ?? null,
                'supplier_price' => ($data['cost'] !== null && $data['cost'] >= 0) ? $data['cost'] : null,
                'pack_type' => 'loose',
                'pack_size' => $data['pack_size'] ?? null,
                'is_primary' => false,
            ]);

            $this->stats['medicine_supplier_created']++;
        } catch (\Exception $e) {
            $this->stats['errors'][] = "Failed to create medicine_supplier: " . $e->getMessage();
        }
    }

    /**
     * Reset statistics
     */
    protected function resetStats(): void
    {
        $this->stats = [
            'total_rows' => 0,
            'processed_rows' => 0,
            'suppliers_created' => 0,
            'suppliers_updated' => 0,
            'companies_created' => 0,
            'companies_updated' => 0,
            'medicines_created' => 0,
            'medicines_updated' => 0,
            'medicine_supplier_created' => 0,
            'errors' => [],
            'warnings' => [],
            'duplicates_skipped' => 0,
            'skipped_rows' => 0,
            'progress' => 0,
            'current_chunk' => 0,
            'total_chunks' => 0,
        ];
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function formatStats(): string
    {
        $output = [];
        $output[] = '========================================';
        $output[] = '        IMPORT SUMMARY';
        $output[] = '========================================';
        $output[] = "Total rows in CSV: {$this->stats['total_rows']}";
        $output[] = "Rows processed successfully: {$this->stats['processed_rows']}";
        $output[] = "Rows skipped (missing data): {$this->stats['skipped_rows']}";
        $output[] = "Suppliers created: {$this->stats['suppliers_created']}";
        $output[] = "Suppliers updated: {$this->stats['suppliers_updated']}";
        $output[] = "Companies created: {$this->stats['companies_created']}";
        $output[] = "Companies updated: {$this->stats['companies_updated']}";
        $output[] = "Medicines created: {$this->stats['medicines_created']}";
        $output[] = "Medicines updated: {$this->stats['medicines_updated']}";
        $output[] = "Medicine-Supplier relationships: {$this->stats['medicine_supplier_created']}";
        $output[] = "Duplicates skipped: {$this->stats['duplicates_skipped']}";
        
        if (!empty($this->stats['warnings'])) {
            $output[] = '----------------------------------------';
            $output[] = 'WARNINGS (first 50):';
            $count = 0;
            foreach ($this->stats['warnings'] as $warning) {
                if ($count < 50) {
                    $output[] = "  - {$warning}";
                }
                $count++;
            }
            if ($count > 50) {
                $output[] = "  ... and " . ($count - 50) . " more warnings";
            }
        }

        if (!empty($this->stats['errors'])) {
            $output[] = '----------------------------------------';
            $output[] = 'ERRORS:';
            foreach ($this->stats['errors'] as $error) {
                $output[] = "  - {$error}";
            }
        }

        $output[] = '========================================';
        return implode("\n", $output);
    }
}
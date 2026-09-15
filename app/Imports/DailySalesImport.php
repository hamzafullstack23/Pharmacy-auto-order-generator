<?php

namespace App\Imports;

use App\Models\DailySale;
use App\Models\Medicine;
use App\Models\Company;
use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\Importable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

class DailySalesImport implements ToModel, WithHeadingRow, WithChunkReading, WithValidation, SkipsEmptyRows, SkipsOnFailure, SkipsOnError
{
    use Importable;
    
    protected $saleDate;
    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $createdCount = 0;
    protected $errors = [];
    protected $productCodeCache = [];
    protected $autoCreateProducts = true; // Set to false to disable auto-creation

    public function __construct($saleDate)
    {
        $this->saleDate = Carbon::parse($saleDate)->format('Y-m-d');
    }

    /**
     * Prepare the row for validation
     */
    public function prepareForValidation($row)
    {
        $prepared = [];
        
        foreach ($row as $key => $value) {
            $normalizedKey = $this->normalizeColumnName($key);
            $prepared[$normalizedKey] = $value;
        }

        // Handle specific column mappings
        if (isset($prepared['product_code']) && empty($prepared['product_code'])) {
            foreach ($row as $key => $value) {
                if (strpos($key, 'Product Code') !== false) {
                    $prepared['product_code'] = (string) $value;
                    break;
                }
            }
        }

        if (isset($prepared['product_name']) && empty($prepared['product_name'])) {
            foreach ($row as $key => $value) {
                if (strpos($key, 'Product Name') !== false) {
                    $prepared['product_name'] = (string) $value;
                    break;
                }
            }
        }

        if (isset($prepared['product_code']) && $prepared['product_code'] !== '') {
            $prepared['product_code'] = (string) $prepared['product_code'];
        }

        return $prepared;
    }

    /**
     * Normalize column names
     */
    protected function normalizeColumnName($column)
    {
        $column = trim($column);
        
        $exactMatches = [
            'Product Code' => 'product_code',
            'ProductCode' => 'product_code',
            'Product Name' => 'product_name',
            'ProductName' => 'product_name',
            'Quantity' => 'quantity',
            'Date' => 'date',
            'Sale Date' => 'sale_date',
            'Sales Date' => 'sale_date'
        ];
        
        if (isset($exactMatches[$column])) {
            return $exactMatches[$column];
        }
        
        $column = strtolower($column);
        $column = preg_replace('/[^a-z0-9_]/', '_', $column);
        $column = preg_replace('/_+/', '_', $column);
        $column = trim($column, '_');
        
        return $column;
    }

    /**
     * Define validation rules
     */
    public function rules(): array
    {
        return [
            'product_code' => 'required|string',
            'product_name' => 'required|string',
            'quantity' => 'required|numeric|min:0.01',
        ];
    }

    /**
     * Custom validation messages
     */
    public function customValidationMessages()
    {
        return [
            'product_code.required' => 'Product Code is required. Column should be named "Product Code"',
            'product_code.string' => 'Product Code must be a valid text value',
            'product_name.required' => 'Product Name is required. Column should be named "Product Name"',
            'product_name.string' => 'Product Name must be a valid text value',
            'quantity.required' => 'Quantity is required. Column should be named "Quantity"',
            'quantity.numeric' => 'Quantity must be a number',
            'quantity.min' => 'Quantity must be greater than 0',
        ];
    }

    public function model(array $row)
    {
        try {
            // Get values from the prepared row
            $productCode = isset($row['product_code']) ? trim($row['product_code']) : '';
            $productName = isset($row['product_name']) ? trim($row['product_name']) : '';
            $quantity = isset($row['quantity']) ? (float) $row['quantity'] : 0;
            $date = isset($row['date']) ? $row['date'] : null;
            
            if ($quantity == 0) {
                foreach ($row as $key => $value) {
                    if (strpos($key, 'quantity') !== false || strpos($key, 'qty') !== false) {
                        $quantity = (float) $value;
                        break;
                    }
                }
            }

            $saleDate = !empty($date) ? $this->parseDate($date) : $this->saleDate;

            // Validate required fields
            if (empty($productCode)) {
                $this->errors[] = "Row skipped: Product Code is missing";
                $this->skippedCount++;
                return null;
            }

            if (empty($productName)) {
                $this->errors[] = "Row skipped: Product Name is missing for code '{$productCode}'";
                $this->skippedCount++;
                return null;
            }

            if ($quantity <= 0) {
                $this->errors[] = "Row skipped: Invalid quantity ({$quantity}) for '{$productCode}'";
                $this->skippedCount++;
                return null;
            }

            $productCode = trim($productCode);
            
            // Find or create medicine
            $medicine = $this->findOrCreateMedicine($productCode, $productName);

            if (!$medicine) {
                $this->errors[] = "Failed to find or create product for code '{$productCode}'";
                $this->skippedCount++;
                return null;
            }

            // Get company and supplier IDs
            $companyId = $medicine->company_id;
            $supplierId = null;

            if ($medicine->company && $medicine->company->supplier) {
                $supplierId = $medicine->company->supplier->id;
            }

            // Check if sale already exists
            $existingSale = DailySale::where('sale_date', $saleDate)
                                     ->where('medicine_id', $medicine->id)
                                     ->first();

            if ($existingSale) {
                $existingSale->update([
                    'quantity_sold' => $existingSale->quantity_sold + $quantity,
                    'updated_at' => now(),
                ]);
                
                $this->importedCount++;
                Log::info("Updated sale for {$productCode} on {$saleDate}: +{$quantity}");
            } else {
                DailySale::create([
                    'sale_date' => $saleDate,
                    'medicine_name' => $medicine->name,
                    'quantity_sold' => $quantity,
                    'company_id' => $companyId,
                    'supplier_id' => $supplierId,
                    'medicine_id' => $medicine->id,
                    'import_batch' => date('Y-m-d H:i:s'),
                ]);
                
                $this->importedCount++;
                Log::info("Created sale for {$productCode} on {$saleDate}: {$quantity}");
            }

            return null;

        } catch (\Exception $e) {
            $this->errors[] = "Error processing row: " . $e->getMessage();
            $this->skippedCount++;
            Log::error("Import error: " . $e->getMessage(), ['row' => $row]);
            return null;
        }
    }

    /**
     * Find or create a medicine by product code
     */
    protected function findOrCreateMedicine($productCode, $productName)
    {
        // Check cache first
        if (isset($this->productCodeCache[$productCode])) {
            return $this->productCodeCache[$productCode];
        }

        // Try to find existing medicine
        $medicine = $this->findMedicineByCode($productCode);

        // If not found and auto-creation is enabled, create it
        if (!$medicine && $this->autoCreateProducts) {
            $medicine = $this->createMedicine($productCode, $productName);
            
            if ($medicine) {
                $this->createdCount++;
                Log::info("Created new medicine", [
                    'code' => $productCode,
                    'name' => $productName,
                    'id' => $medicine->id
                ]);
            }
        }

        // Cache the result
        if ($medicine) {
            $this->productCodeCache[$productCode] = $medicine;
        }

        return $medicine;
    }

    /**
     * Find medicine by product code
     */
    protected function findMedicineByCode($productCode)
    {
        // Try exact match
        $medicine = Medicine::where('product_code', $productCode)->first();
        
        // Try with leading zeros removed
        if (!$medicine) {
            $trimmedCode = ltrim($productCode, '0');
            if ($trimmedCode !== $productCode) {
                $medicine = Medicine::where('product_code', $trimmedCode)->first();
            }
        }
        
        // Try case-insensitive
        if (!$medicine) {
            $medicine = Medicine::where(DB::raw('UPPER(product_code)'), strtoupper($productCode))->first();
        }
        
        // Try by name (fuzzy match)
        if (!$medicine) {
            $medicine = Medicine::where('name', 'LIKE', '%' . $productCode . '%')->first();
        }

        return $medicine;
    }

    /**
     * Create a new medicine
     */
    protected function createMedicine($productCode, $productName)
    {
        try {
            // Try to find or create a default company
            $company = Company::first();
            if (!$company) {
                $company = Company::create([
                    'name' => 'Default Company',
                    'code' => 'DEF',
                    'status' => 'active'
                ]);
            }

            // Create the medicine
            $medicine = Medicine::create([
                'product_code' => $productCode,
                'name' => $productName,
                'company_id' => $company->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $medicine;

        } catch (\Exception $e) {
            Log::error('Failed to create medicine', [
                'code' => $productCode,
                'name' => $productName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function parseDate($dateString)
    {
        if (empty($dateString)) {
            return $this->saleDate;
        }
        
        $dateString = trim($dateString);
        
        $formats = [
            'm/d/Y',
            'd/m/Y',
            'Y-m-d',
            'm/d/Y H:i:s',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s',
            'M d, Y',
            'd M Y'
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

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getSkippedCount()
    {
        return $this->skippedCount;
    }

    public function getCreatedCount()
    {
        return $this->createdCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $errorMessages = implode(', ', $failure->errors());
            $this->errors[] = "Row {$failure->row()} skipped: {$errorMessages}";
            $this->skippedCount++;
            Log::warning('Row validation failure', [
                'row' => $failure->row(),
                'errors' => $failure->errors(),
                'values' => $failure->values()
            ]);
        }
    }

    public function onError(Throwable $e)
    {
        $this->errors[] = "Row error: " . $e->getMessage();
        $this->skippedCount++;
        Log::error('Uncaught row processing error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
<?php

namespace App\Imports;

use App\Models\DailySale;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Medicine;
use App\Models\AccumulatedSale;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;
use Carbon\Carbon;

class DailySalesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    protected $saleDate;
    protected $importedCount = 0;
    protected $errors = [];
    protected $companyCache = [];
    protected $medicineCache = [];

    public function __construct($saleDate)
    {
        $this->saleDate = $saleDate;
    }

    public function model(array $row)
    {
        // Find company by name
        $company = $this->getCompany($row['company_name']);
        
        if (!$company) {
            $this->errors[] = "Company '{$row['company_name']}' not found. Please create the company first.";
            return null;
        }

        // Find or get medicine
        $medicine = $this->getMedicine($row['medicine_name'], $company->id);
        
        // Check for duplicate
        $existing = DailySale::where('sale_date', $this->saleDate)
                             ->where('medicine_name', $row['medicine_name'])
                             ->where('company_id', $company->id)
                             ->first();
        
        if ($existing) {
            // Update quantity if exists
            $existing->quantity_sold += (int) $row['quantity_sold'];
            $existing->save();
            $this->importedCount++;
            
            // Update accumulated sales
            $this->updateAccumulatedSales($company->supplier_id, $company->id, $medicine?->id, $existing->quantity_sold);
            
            return null;
        }

        $this->importedCount++;
        
        // Create daily sale
        $dailySale = new DailySale([
            'sale_date' => $this->saleDate,
            'medicine_name' => $row['medicine_name'],
            'quantity_sold' => (int) $row['quantity_sold'],
            'company_id' => $company->id,
            'supplier_id' => $company->supplier_id,
            'medicine_id' => $medicine?->id,
            'import_batch' => Carbon::now()->format('YmdHis'),
        ]);
        
        // Update accumulated sales
        $this->updateAccumulatedSales($company->supplier_id, $company->id, $medicine?->id, (int) $row['quantity_sold']);
        
        return $dailySale;
    }

    public function rules(): array
    {
        return [
            'medicine_name' => 'required|string|max:255',
            'quantity_sold' => 'required|integer|min:1',
            'company_name' => 'required|string|max:255',
        ];
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
        }
    }

    protected function getCompany($name)
    {
        if (!isset($this->companyCache[$name])) {
            $this->companyCache[$name] = Company::where('name', $name)->first();
        }
        return $this->companyCache[$name];
    }

    protected function getMedicine($name, $companyId)
    {
        $key = $name . '_' . $companyId;
        if (!isset($this->medicineCache[$key])) {
            $this->medicineCache[$key] = Medicine::where('name', $name)
                                                ->where('company_id', $companyId)
                                                ->first();
        }
        return $this->medicineCache[$key];
    }

    protected function updateAccumulatedSales($supplierId, $companyId, $medicineId, $quantity)
    {
        $today = Carbon::today();
        $startDate = $today->copy()->subDay();
        $endDate = $today;
        
        // Find or create accumulated sale
        $accumulated = AccumulatedSale::firstOrCreate(
            [
                'supplier_id' => $supplierId,
                'company_id' => $companyId,
                'medicine_id' => $medicineId,
                'accumulation_start_date' => $startDate->toDateString(),
                'accumulation_end_date' => $endDate->toDateString(),
                'is_cleared' => false,
            ],
            [
                'total_quantity' => 0,
            ]
        );
        
        $accumulated->total_quantity += $quantity;
        $accumulated->save();
        
        return $accumulated;
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
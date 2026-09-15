<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySale extends Model
{
    use HasFactory;
    // Remove: use SoftDeletes;

    protected $fillable = [
        'sale_date',
        'medicine_name',
        'quantity_sold',
        'company_id',
        'supplier_id',
        'medicine_id',
        'import_batch',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'quantity_sold' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the medicine associated with the sale
     */
    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    /**
     * Get the company associated with the sale
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the supplier associated with the sale
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Scope a query to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by company
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the total quantity sold for a specific date
     */
    public static function getTotalByDate($date)
    {
        return self::whereDate('sale_date', $date)->sum('quantity_sold');
    }
}
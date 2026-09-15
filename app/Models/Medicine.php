<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'name',
        'company_id',
        'unit',
        'cost',
        'pack_type',
        'pack_size',
        'max_stock_limit',
        'current_stock',
        'is_active',
        'category_name',
        'sub_category_name',
        'generics',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'max_stock_limit' => 'integer',
        'current_stock' => 'integer',
        'is_active' => 'boolean',
        'pack_size' => 'integer',
    ];

    /**
     * Get the company that owns this medicine.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the suppliers for this medicine through the pivot table.
     * This is the MAIN relationship for suppliers.
     */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'medicine_supplier')
                    ->withPivot('id', 'is_primary', 'supplier_medicine_code', 'supplier_price', 'pack_type', 'pack_size')
                    ->withTimestamps();
    }

    /**
     * Get the primary supplier for this medicine.
     */
    public function primarySupplier()
    {
        return $this->belongsToMany(Supplier::class, 'medicine_supplier')
                    ->wherePivot('is_primary', true)
                    ->withPivot('supplier_medicine_code', 'supplier_price')
                    ->withTimestamps();
    }

    /**
     * Get the supplier through the company (legacy - keep for backwards compatibility).
     */
    public function supplier()
    {
        return $this->hasOneThrough(Supplier::class, Company::class);
    }

    /**
     * Get the daily sales for this medicine.
     */
    public function dailySales()
    {
        return $this->hasMany(DailySale::class);
    }

    /**
     * Get the accumulated sales for this medicine.
     */
    public function accumulatedSales()
    {
        return $this->hasMany(AccumulatedSale::class);
    }

    /**
     * Get the order items for this medicine.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope a query to only include active medicines.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by stock level.
     */
    public function scopeStockLevel($query, $level)
    {
        switch ($level) {
            case 'low':
                return $query->where('current_stock', '<', 10);
            case 'medium':
                return $query->whereBetween('current_stock', [10, 50]);
            case 'high':
                return $query->where('current_stock', '>', 50);
            case 'out':
                return $query->where('current_stock', 0);
            default:
                return $query;
        }
    }

    /**
     * Scope a query to search by name or product code.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%' . $search . '%')
                     ->orWhere('product_code', 'like', '%' . $search . '%');
    }

    /**
     * Check if medicine is in stock.
     */
    public function isInStock(): bool
    {
        return $this->current_stock > 0;
    }

    /**
     * Check if medicine is low in stock.
     */
    public function isLowStock(): bool
    {
        return $this->current_stock < 10;
    }

    /**
     * Get the stock status label.
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->current_stock <= 0) {
            return 'Out of Stock';
        } elseif ($this->current_stock < 10) {
            return 'Low Stock';
        } elseif ($this->current_stock < 50) {
            return 'Medium Stock';
        } else {
            return 'In Stock';
        }
    }

    /**
     * Get the stock status color.
     */
    public function getStockStatusColorAttribute(): string
    {
        if ($this->current_stock <= 0) {
            return 'red';
        } elseif ($this->current_stock < 10) {
            return 'yellow';
        } elseif ($this->current_stock < 50) {
            return 'blue';
        } else {
            return 'green';
        }
    }

    /**
     * Get the display name with product code.
     */
    public function getDisplayNameAttribute(): string
    {
        return ($this->product_code ? $this->product_code . ' - ' : '') . $this->name;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'order_day',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_day' => 'string',
    ];

    /**
     * Get the companies for this supplier.
     */
    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Get the medicines for this supplier through the pivot table.
     * This is the MAIN relationship for medicines.
     */
    public function medicines()
    {
        return $this->belongsToMany(Medicine::class, 'medicine_supplier')
                    ->withPivot('id', 'is_primary', 'supplier_medicine_code', 'supplier_price', 'pack_type', 'pack_size')
                    ->withTimestamps();
    }

    /**
     * Get the medicines through companies (legacy - keep for backwards compatibility).
     */
    public function medicinesThroughCompanies()
    {
        return $this->hasManyThrough(Medicine::class, Company::class);
    }

    /**
     * Get the orders for this supplier.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the daily sales for this supplier.
     */
    public function dailySales()
    {
        return $this->hasMany(DailySale::class);
    }

    /**
     * Get the accumulated sales for this supplier.
     */
    public function accumulatedSales()
    {
        return $this->hasMany(AccumulatedSale::class);
    }

    /**
     * Get the order day name attribute.
     */
    public function getOrderDayNameAttribute()
    {
        return ucfirst($this->order_day);
    }

    /**
     * Scope a query to only include active suppliers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%' . $search . '%')
                     ->orWhere('contact_person', 'like', '%' . $search . '%');
    }

    /**
     * Get the display name for the supplier.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ($this->contact_person ? ' (' . $this->contact_person . ')' : '');
    }

    /**
     * Check if supplier has any medicines.
     */
    public function hasMedicines(): bool
    {
        return $this->medicines()->count() > 0;
    }
}
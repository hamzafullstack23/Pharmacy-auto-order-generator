<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'supplier_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function medicines()
    {
        return $this->hasMany(Medicine::class);
    }

    public function dailySales()
    {
        return $this->hasMany(DailySale::class);
    }

    public function accumulatedSales()
    {
        return $this->hasMany(AccumulatedSale::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineSupplier extends Model
{
    use HasFactory;
    protected $table = 'medicine_supplier';

    protected $fillable = [
        'medicine_id',
        'supplier_id',
        'supplier_medicine_code',
        'supplier_price',
        'pack_type',
        'pack_size',
        'is_primary',
    ];

    protected $casts = [
        'supplier_price' => 'decimal:2',
        'pack_size' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
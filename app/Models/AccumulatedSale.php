<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccumulatedSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'company_id',
        'medicine_id',
        'total_quantity',
        'accumulation_start_date',
        'accumulation_end_date',
        'is_cleared',
        'cleared_at',
        'cleared_by_order_id',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'accumulation_start_date' => 'date',
        'accumulation_end_date' => 'date',
        'is_cleared' => 'boolean',
        'cleared_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function clearedByOrder()
    {
        return $this->belongsTo(Order::class, 'cleared_by_order_id');
    }
}
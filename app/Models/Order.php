<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'supplier_id',
        'user_id',
        'order_date',
        'delivery_date',
        'status',
        'notes',
        'total_amount',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function medicines()
    {
        return $this->belongsToMany(Medicine::class, 'order_items')
                    ->withPivot('quantity', 'pack_type', 'pack_size', 'unit_price', 'total_price')
                    ->withTimestamps();
    }

    public function accumulatedSales()
    {
        return $this->hasMany(AccumulatedSale::class, 'cleared_by_order_id');
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function canBeModified()
    {
        return $this->isDraft();
    }
}
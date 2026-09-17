<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = [
        'import_batch_id', 'row_number', 'product_code', 'product_name',
        'quantity', 'sale_date', 'status', 'medicine_id', 'supplier_id',
        'company_id', 'error_message',
        'is_exported', 'exported_at', 'export_batch_id',
    ];

    protected $casts = [
        'quantity'    => 'decimal:2',
        'sale_date'   => 'date',
        'is_exported' => 'boolean',
        'exported_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function exportBatch(): BelongsTo
    {
        return $this->belongsTo(ExportBatch::class, 'export_batch_id');
    }

    public function scopeUnexported($query)
    {
        return $query->where('is_exported', false);
    }

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }
}
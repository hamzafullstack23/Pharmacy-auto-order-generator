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
    ];

    protected $casts = [
        'quantity'  => 'decimal:2',
        'sale_date' => 'date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
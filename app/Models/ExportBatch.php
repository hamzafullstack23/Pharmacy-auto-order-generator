<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExportBatch extends Model
{
    protected $fillable = [
        'export_uuid', 'supplier_id', 'supplier_name',
        'rows_count', 'total_quantity', 'filename',
    ];

    protected $casts = [
        'total_quantity' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'export_batch_id');
    }
}
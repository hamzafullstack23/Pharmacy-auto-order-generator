<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'batch_uuid', 'status', 'sale_date', 'original_filename',
        'missing_entities', 'stats',
    ];

    protected $casts = [
        'missing_entities' => 'array',
        'stats'            => 'array',
        'sale_date'        => 'date',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function pendingRows(): HasMany
    {
        return $this->rows()->where('status', 'pending');
    }
}
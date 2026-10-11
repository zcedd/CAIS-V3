<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispersalBatchItemLine extends Model
{
    protected $fillable = [
        'dispersal_batch_item_id',
        'assistance_item_id',
        'item_id',
        'proposed_quantity',
        'approved_quantity',
        'proposed_amount',
        'approved_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_quantity' => 'decimal:2',
            'approved_quantity' => 'decimal:2',
            'proposed_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
        ];
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DispersalBatchItem::class, 'dispersal_batch_item_id');
    }

    public function assistanceItem(): BelongsTo
    {
        return $this->belongsTo(AssistanceItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(DispersalReleaseRecord::class);
    }
}

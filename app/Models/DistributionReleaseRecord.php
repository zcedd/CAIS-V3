<?php

namespace App\Models;

use App\Enums\DistributionReleaseOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionReleaseRecord extends Model
{
    protected $fillable = [
        'distribution_batch_item_id',
        'distribution_batch_item_line_id',
        'distribution_transaction_id',
        'assistance_item_id',
        'outcome',
        'actual_quantity',
        'actual_amount',
        'released_at',
        'released_by',
        'receipt_reference',
        'identity_verified',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DistributionReleaseOutcome::class,
            'actual_quantity' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'released_at' => 'datetime',
            'identity_verified' => 'boolean',
        ];
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DistributionBatchItem::class, 'distribution_batch_item_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(DistributionBatchItemLine::class, 'distribution_batch_item_line_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DistributionTransaction::class, 'distribution_transaction_id');
    }

    public function assistanceItem(): BelongsTo
    {
        return $this->belongsTo(AssistanceItem::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}

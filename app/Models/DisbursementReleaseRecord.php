<?php

namespace App\Models;

use App\Enums\DisbursementReleaseOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementReleaseRecord extends Model
{
    protected $fillable = [
        'disbursement_batch_item_id',
        'disbursement_batch_item_line_id',
        'disbursement_transaction_id',
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
            'outcome' => DisbursementReleaseOutcome::class,
            'actual_quantity' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'released_at' => 'datetime',
            'identity_verified' => 'boolean',
        ];
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatchItem::class, 'disbursement_batch_item_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatchItemLine::class, 'disbursement_batch_item_line_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DisbursementTransaction::class, 'disbursement_transaction_id');
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

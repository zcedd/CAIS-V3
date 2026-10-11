<?php

namespace App\Models;

use App\Enums\DisbursementDecisionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementApprovalDecision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'disbursement_batch_id',
        'disbursement_batch_item_id',
        'disbursement_approval_task_id',
        'disbursement_approval_stage_id',
        'revision',
        'actor_id',
        'previous_decision',
        'decision',
        'previous_amount',
        'new_amount',
        'previous_quantity',
        'new_quantity',
        'remarks',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'decision' => DisbursementDecisionAction::class,
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'previous_quantity' => 'decimal:2',
            'new_quantity' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatch::class, 'disbursement_batch_id');
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatchItem::class, 'disbursement_batch_item_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DisbursementApprovalTask::class, 'disbursement_approval_task_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DisbursementApprovalStage::class, 'disbursement_approval_stage_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

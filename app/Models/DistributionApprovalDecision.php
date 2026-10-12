<?php

namespace App\Models;

use App\Enums\DistributionDecisionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionApprovalDecision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'distribution_batch_id',
        'distribution_batch_item_id',
        'distribution_approval_task_id',
        'distribution_approval_stage_id',
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
            'decision' => DistributionDecisionAction::class,
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'previous_quantity' => 'decimal:2',
            'new_quantity' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DistributionBatch::class, 'distribution_batch_id');
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DistributionBatchItem::class, 'distribution_batch_item_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DistributionApprovalTask::class, 'distribution_approval_task_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DistributionApprovalStage::class, 'distribution_approval_stage_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

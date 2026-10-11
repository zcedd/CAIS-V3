<?php

namespace App\Models;

use App\Enums\DispersalDecisionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispersalApprovalDecision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'dispersal_batch_id',
        'dispersal_batch_item_id',
        'dispersal_approval_task_id',
        'dispersal_approval_stage_id',
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
            'decision' => DispersalDecisionAction::class,
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'previous_quantity' => 'decimal:2',
            'new_quantity' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DispersalBatch::class, 'dispersal_batch_id');
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DispersalBatchItem::class, 'dispersal_batch_item_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DispersalApprovalTask::class, 'dispersal_approval_task_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DispersalApprovalStage::class, 'dispersal_approval_stage_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

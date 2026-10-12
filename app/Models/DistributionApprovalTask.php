<?php

namespace App\Models;

use App\Enums\DistributionApprovalTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionApprovalTask extends Model
{
    protected $fillable = [
        'distribution_batch_id',
        'distribution_approval_stage_id',
        'revision',
        'status',
        'completed_at',
        'completed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'status' => DistributionApprovalTaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DistributionBatch::class, 'distribution_batch_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DistributionApprovalStage::class, 'distribution_approval_stage_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

<?php

namespace App\Models;

use App\Enums\DispersalApprovalTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispersalApprovalTask extends Model
{
    protected $fillable = [
        'dispersal_batch_id',
        'dispersal_approval_stage_id',
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
            'status' => DispersalApprovalTaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DispersalBatch::class, 'dispersal_batch_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DispersalApprovalStage::class, 'dispersal_approval_stage_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

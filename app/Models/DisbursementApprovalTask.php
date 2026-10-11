<?php

namespace App\Models;

use App\Enums\DisbursementApprovalTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementApprovalTask extends Model
{
    protected $fillable = [
        'disbursement_batch_id',
        'disbursement_approval_stage_id',
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
            'status' => DisbursementApprovalTaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatch::class, 'disbursement_batch_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DisbursementApprovalStage::class, 'disbursement_approval_stage_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

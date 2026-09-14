<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepTransition extends Model
{
    protected $fillable = [
        'workflow_step_id',
        'to_request_status_id',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function toRequestStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'to_request_status_id');
    }
}

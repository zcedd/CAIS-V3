<?php

namespace App\Models;

use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'request_status_id',
        'sort_order',
        'default_request_sub_status_id',
        'sla_hours',
        'requires_assignee',
        'assigned_to_id',
        'permission',
        'allows_skip_to_deliver',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'sla_hours' => 'integer',
            'requires_assignee' => 'boolean',
            'allows_skip_to_deliver' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function requestStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class);
    }

    public function defaultSubStatus(): BelongsTo
    {
        return $this->belongsTo(RequestSubStatus::class, 'default_request_sub_status_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowStepTransition::class);
    }

    /**
     * @return list<int>
     */
    public function allowedTargetStatusIds(): array
    {
        return $this->transitions
            ->map(static fn (WorkflowStepTransition $transition): int => (int) $transition->to_request_status_id)
            ->unique()
            ->values()
            ->all();
    }
}

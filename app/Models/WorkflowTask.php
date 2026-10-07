<?php

namespace App\Models;

use App\Enums\WorkflowTaskPriority;
use App\Enums\WorkflowTaskStatus;
use Database\Factories\WorkflowTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTask extends Model
{
    /** @use HasFactory<WorkflowTaskFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => WorkflowTaskStatus::Pending->value,
        'priority' => WorkflowTaskPriority::Normal->value,
    ];

    protected $fillable = [
        'assistance_workflow_id',
        'workflow_step_id',
        'assigned_to_id',
        'assigned_by_id',
        'status',
        'priority',
        'assigned_at',
        'started_at',
        'due_at',
        'completed_at',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowTaskStatus::class,
            'priority' => WorkflowTaskPriority::class,
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(AssistanceWorkflow::class, 'assistance_workflow_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WorkflowTaskHistory::class)->orderBy('id');
    }

    /**
     * @param  Builder<WorkflowTask>  $query
     * @return Builder<WorkflowTask>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WorkflowTaskStatus::Pending,
            WorkflowTaskStatus::Claimed,
            WorkflowTaskStatus::InProgress,
        ]);
    }

    public function isOpen(): bool
    {
        return ($this->status ?? WorkflowTaskStatus::Pending)->isOpen();
    }
}

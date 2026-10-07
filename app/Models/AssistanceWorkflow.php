<?php

namespace App\Models;

use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowTaskStatus;
use Database\Factories\AssistanceWorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssistanceWorkflow extends Model
{
    /** @use HasFactory<AssistanceWorkflowFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => WorkflowInstanceStatus::Active->value,
        'workflow_version' => 1,
    ];

    protected $fillable = [
        'assistance_id',
        'workflow_id',
        'workflow_version',
        'current_step_id',
        'status',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workflow_version' => 'integer',
            'status' => WorkflowInstanceStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class);
    }

    public function request(): BelongsTo
    {
        return $this->assistance();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function currentTask(): HasOne
    {
        return $this->hasOne(WorkflowTask::class)
            ->whereIn('status', [
                WorkflowTaskStatus::Pending->value,
                WorkflowTaskStatus::Claimed->value,
                WorkflowTaskStatus::InProgress->value,
            ])
            ->latestOfMany();
    }

    public function isOpen(): bool
    {
        return ($this->status ?? WorkflowInstanceStatus::Active)->isOpen();
    }
}

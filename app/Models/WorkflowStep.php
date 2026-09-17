<?php

namespace App\Models;

use App\Enums\RequestStatusCode;
use App\Enums\WorkflowAssignmentType;
use App\Enums\WorkflowStepType;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_start' => false,
        'is_end' => false,
        'requires_assignee' => false,
        'automatic_assignment' => false,
        'allows_skip_to_deliver' => false,
        'assignment_type' => WorkflowAssignmentType::None->value,
        'step_type' => WorkflowStepType::Custom->value,
    ];

    protected $fillable = [
        'workflow_id',
        'code',
        'name',
        'step_type',
        'request_status_id',
        'sort_order',
        'is_start',
        'is_end',
        'default_request_sub_status_id',
        'sla_hours',
        'requires_assignee',
        'assignment_type',
        'assigned_role',
        'assigned_department_id',
        'assigned_to_id',
        'automatic_assignment',
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
            'is_start' => 'boolean',
            'is_end' => 'boolean',
            'requires_assignee' => 'boolean',
            'automatic_assignment' => 'boolean',
            'allows_skip_to_deliver' => 'boolean',
            'assignment_type' => WorkflowAssignmentType::class,
            'step_type' => WorkflowStepType::class,
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

    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
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
        $this->loadMissing(['transitions.toStep', 'transitions.toRequestStatus']);

        return $this->transitions
            ->map(static function (WorkflowStepTransition $transition): int {
                return (int) ($transition->toStep?->request_status_id ?? $transition->to_request_status_id);
            })
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function allowedTargetStepIds(): array
    {
        return $this->transitions
            ->map(static fn (WorkflowStepTransition $transition): int => (int) ($transition->to_step_id ?? 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function displayName(): string
    {
        if (is_string($this->name) && trim($this->name) !== '') {
            return $this->name;
        }

        if (is_string($this->code) && trim($this->code) !== '') {
            return str_replace('_', ' ', $this->code);
        }

        return $this->requestStatus?->name ?? 'Step';
    }

    public function isTaskStep(): bool
    {
        $type = $this->step_type instanceof WorkflowStepType
            ? $this->step_type
            : WorkflowStepType::tryFrom((string) $this->step_type);

        if ($this->is_end || $type === WorkflowStepType::Completion || $type === WorkflowStepType::Hold || $type === WorkflowStepType::Rejection) {
            return false;
        }

        $status = $this->requestStatus;

        if (RequestStatusCode::Closed->matches($status)
            || RequestStatusCode::OnHold->matches($status)
            || RequestStatusCode::Denied->matches($status)) {
            return false;
        }

        return true;
    }
}

<?php

namespace App\Models;

use App\Enums\WorkflowTransitionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepTransition extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'action' => WorkflowTransitionAction::Advance->value,
        'requires_comment' => false,
    ];

    protected $fillable = [
        'workflow_step_id',
        'to_step_id',
        'to_request_status_id',
        'action',
        'label',
        'requires_comment',
        'conditions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => WorkflowTransitionAction::class,
            'requires_comment' => 'boolean',
            'conditions' => 'array',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function fromStep(): BelongsTo
    {
        return $this->step();
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'to_step_id');
    }

    public function toRequestStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'to_request_status_id');
    }

    public function displayLabel(): string
    {
        if (is_string($this->label) && trim($this->label) !== '') {
            return $this->label;
        }

        $action = $this->action instanceof WorkflowTransitionAction
            ? $this->action->label()
            : 'Advance';
        $destination = $this->toStep?->displayName()
            ?? $this->toRequestStatus?->name
            ?? 'next step';

        return $action.' to '.$destination;
    }
}

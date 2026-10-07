<?php

namespace App\Http\Requests\User\WorkflowTask;

use App\Enums\WorkflowTransitionAction;
use App\Http\Requests\User\WorkflowTask\Concerns\AuthorizesWorkflowTask;
use App\Models\WorkflowStepTransition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompleteRequest extends FormRequest
{
    use AuthorizesWorkflowTask;

    public function authorize(): bool
    {
        return Gate::allows('advance', $this->assistance());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transition_id' => ['required', 'integer', 'exists:workflow_step_transitions,id'],
            'action' => ['nullable', Rule::enum(WorkflowTransitionAction::class)],
            'remark' => ['nullable', 'string'],
            'recorded_at' => ['nullable', 'date'],
            'request_sub_status_id' => ['nullable', 'integer', 'exists:request_sub_statuses,id'],
        ];
    }

    public function transition(): WorkflowStepTransition
    {
        return WorkflowStepTransition::query()->findOrFail($this->integer('transition_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'remark' => $this->validated('remark'),
            'recorded_at' => $this->validated('recorded_at') ?? now()->toDateTimeString(),
            'request_sub_status_id' => $this->validated('request_sub_status_id'),
        ];
    }
}

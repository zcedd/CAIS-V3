<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Enums\WorkflowAssignmentType;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('workflow');

        return $workflow instanceof Workflow && Gate::allows('update', $workflow);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['nullable', 'boolean'],
            'staff_entry_request_status_id' => ['nullable', 'integer', 'exists:request_statuses,id'],
            'steps' => ['nullable', 'array'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.code' => ['nullable', 'string', 'max:64'],
            'steps.*.name' => ['nullable', 'string', 'max:255'],
            'steps.*.step_type' => ['nullable', 'string', 'max:32'],
            'steps.*.request_status_id' => ['required', 'integer', 'exists:request_statuses,id'],
            'steps.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'steps.*.is_start' => ['nullable', 'boolean'],
            'steps.*.is_end' => ['nullable', 'boolean'],
            'steps.*.default_request_sub_status_id' => ['nullable', 'integer', 'exists:request_sub_statuses,id'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'steps.*.assignment_type' => ['nullable', Rule::enum(WorkflowAssignmentType::class)],
            'steps.*.assigned_role' => ['nullable', 'string', 'max:64'],
            'steps.*.assigned_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'steps.*.assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'steps.*.automatic_assignment' => ['nullable', 'boolean'],
            'steps.*.allows_skip_to_deliver' => ['nullable', 'boolean'],
            'steps.*.transition_status_ids' => ['nullable', 'array'],
            'steps.*.transition_status_ids.*' => ['integer', 'exists:request_statuses,id'],
        ];
    }
}

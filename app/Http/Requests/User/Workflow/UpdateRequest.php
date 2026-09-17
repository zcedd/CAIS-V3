<?php

namespace App\Http\Requests\User\Workflow;

use App\Enums\WorkflowAssignmentType;
use App\Models\Department;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');
        $workflow = $this->route('workflow');

        return $department instanceof Department
            && $workflow instanceof Workflow
            && $workflow->department_id === $department->id
            && Gate::allows('update', $workflow);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['nullable', 'boolean'],
            'staff_entry_request_status_id' => ['nullable', 'integer', 'exists:request_statuses,id'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.code' => ['nullable', 'string', 'max:64'],
            'steps.*.name' => ['nullable', 'string', 'max:255'],
            'steps.*.step_type' => ['nullable', 'string', 'max:32'],
            'steps.*.is_start' => ['nullable', 'boolean'],
            'steps.*.is_end' => ['nullable', 'boolean'],
            'steps.*.assignment_type' => ['nullable', Rule::enum(WorkflowAssignmentType::class)],
            'steps.*.assigned_role' => ['nullable', 'string', 'max:64'],
            'steps.*.assigned_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'steps.*.automatic_assignment' => ['nullable', 'boolean'],
            'steps.*.request_status_id' => ['required', 'integer', 'exists:request_statuses,id'],
            'steps.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'steps.*.default_request_sub_status_id' => ['nullable', 'integer', 'exists:request_sub_statuses,id'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'steps.*.assigned_to_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('department_id', $this->route('department')?->id),
                ),
            ],
            'steps.*.allows_skip_to_deliver' => ['nullable', 'boolean'],
            'steps.*.transition_status_ids' => ['nullable', 'array'],
            'steps.*.transition_status_ids.*' => ['integer', 'exists:request_statuses,id'],
        ];
    }
}

<?php

namespace App\Http\Requests\User\Workflow;

use App\Enums\WorkflowTemplate;
use App\Models\Department;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && Gate::allows('create', [Workflow::class, $department]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template' => ['nullable', Rule::enum(WorkflowTemplate::class)],
            'is_default' => ['nullable', 'boolean'],
            'staff_entry_request_status_id' => ['nullable', 'integer', 'exists:request_statuses,id'],
            'steps' => ['nullable', 'array'],
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

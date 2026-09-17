<?php

namespace App\Http\Requests\User\WorkflowTask;

use App\Http\Requests\User\WorkflowTask\Concerns\AuthorizesWorkflowTask;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignRequest extends FormRequest
{
    use AuthorizesWorkflowTask;

    public function authorize(): bool
    {
        return Gate::allows('assign', $this->assistance());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $departmentId = $this->assistance()->program?->department_id;

        return [
            'assigned_to_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('department_id', $departmentId),
                ),
            ],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function assignee(): ?User
    {
        $id = $this->integer('assigned_to_id');

        if ($id === 0) {
            return null;
        }

        return User::query()->find($id);
    }
}

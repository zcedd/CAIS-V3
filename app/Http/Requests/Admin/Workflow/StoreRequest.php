<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Models\Department;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = Department::query()->find($this->integer('department_id'));

        return $department instanceof Department
            && Gate::allows('create', [Workflow::class, $department]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $departmentId = $this->integer('department_id');
        $version = $this->integer('version') ?: 1;

        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('workflows', 'code')->where(
                    fn ($query) => $query
                        ->where('department_id', $departmentId)
                        ->where('version', $version)
                        ->whereNull('deleted_at'),
                ),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'version' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }
}

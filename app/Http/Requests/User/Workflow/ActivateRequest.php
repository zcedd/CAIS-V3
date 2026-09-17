<?php

namespace App\Http\Requests\User\Workflow;

use App\Models\Department;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ActivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');
        $workflow = $this->route('workflow');

        return $department instanceof Department
            && $workflow instanceof Workflow
            && $workflow->department_id === $department->id
            && Gate::allows('manage', $workflow);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

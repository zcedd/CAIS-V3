<?php

namespace App\Http\Requests\User\Program;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SubmitApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->programInDepartment() && Gate::allows('submit', $this->route('program'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    private function programInDepartment(): bool
    {
        $program = $this->route('program');
        $department = $this->route('department');

        return $program instanceof Program
            && $department instanceof Department
            && $program->department_id === $department->id;
    }
}

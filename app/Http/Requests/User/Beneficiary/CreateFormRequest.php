<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class CreateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->userBelongsToDepartment();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}

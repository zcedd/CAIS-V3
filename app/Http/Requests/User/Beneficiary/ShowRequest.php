<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class ShowRequest extends FormRequest
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
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}

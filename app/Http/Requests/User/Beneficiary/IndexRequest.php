<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
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
            'type' => ['nullable', 'array'],
            'type.*' => ['string', Rule::in(['individual', 'organization'])],
        ];
    }

    public function search(): string
    {
        return trim($this->validated('search') ?? '');
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_values($this->validated('type') ?? []);
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}

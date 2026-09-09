<?php

namespace App\Http\Requests\Admin\User;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $departmentId = $this->input('department_id');

        $this->merge([
            'department_id' => $departmentId === '' || $departmentId === 'none' ? null : $departmentId,
            'roles' => array_values(array_filter(
                (array) $this->input('roles', []),
                static fn (mixed $role): bool => is_string($role) && $role !== '',
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::enum(RoleName::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'firstName' => 'first name',
            'middleName' => 'middle name',
            'lastName' => 'last name',
            'department_id' => 'department',
            'roles' => 'roles',
        ];
    }
}

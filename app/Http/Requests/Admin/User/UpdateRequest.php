<?php

namespace App\Http\Requests\Admin\User;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && Gate::allows('update', $user);
    }

    protected function prepareForValidation(): void
    {
        $departmentId = $this->input('department_id');
        $password = $this->input('password');

        $office = $this->input('office');
        $roles = is_string($office) && $office !== ''
            ? [$office]
            : array_values(array_filter(
                (array) $this->input('roles', []),
                static fn (mixed $role): bool => is_string($role) && $role !== '',
            ));

        $this->merge([
            'department_id' => $departmentId === '' || $departmentId === 'none' ? null : $departmentId,
            'roles' => $roles,
            'password' => is_string($password) && $password === '' ? null : $password,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'department_id' => [
                Rule::requiredIf(fn (): bool => $this->officeRequiresDepartment()),
                'nullable',
                'integer',
                'exists:departments,id',
            ],
            'roles' => ['required', 'array', 'size:1'],
            'roles.*' => [Rule::in(RoleName::officeRoleValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Choose an office.',
            'roles.size' => 'Choose one office.',
            'department_id.required' => 'A department is required for this office.',
        ];
    }

    private function officeRequiresDepartment(): bool
    {
        $office = RoleName::tryFrom((string) ($this->input('roles.0') ?? ''));

        return $office?->requiresDepartment() ?? false;
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

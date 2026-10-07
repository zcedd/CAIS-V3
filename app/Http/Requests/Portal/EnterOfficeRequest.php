<?php

namespace App\Http\Requests\Portal;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EnterOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'office' => ['required', 'string', Rule::in(RoleName::officeRoleValues())],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $office = RoleName::tryFrom((string) $this->input('office'));

            if (! $user instanceof User || $office === null) {
                return;
            }

            $allowed = $user->isSuperAdmin()
                ? RoleName::officeRoleValues()
                : array_values(array_filter(
                    RoleName::officeRoleValues(),
                    fn (string $value): bool => $user->hasRole($value),
                ));

            if (! in_array($office->value, $allowed, true)) {
                $validator->errors()->add('office', 'You cannot enter this office.');
            }

            if ($office->requiresDepartment() && $user->department_id === null && ! $this->filled('department_id')) {
                $validator->errors()->add('department_id', 'Choose a department for this office.');
            }
        });
    }
}

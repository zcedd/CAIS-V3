<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Concerns\ValidatesDuplicateBeneficiaries;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrganizationRequest extends FormRequest
{
    use ValidatesDuplicateBeneficiaries;

    public function authorize(): bool
    {
        return $this->userBelongsToDepartment();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->duplicateAcknowledgementRules(),
            'name' => ['required', 'string', 'max:255'],
            'beneficiary_id' => ['required', 'integer', Rule::exists('individuals', 'id')],
            'address_barangay_id' => ['required', 'integer', Rule::exists('address_barangays', 'id')],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'total_member' => ['nullable', 'integer', 'min:0'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', Rule::exists('individuals', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterDuplicateCheck($validator, $this->duplicateSearchInput());
    }

    /**
     * @return array{
     *     type: 'organization',
     *     name: string|null,
     *     address_barangay_id: int|null,
     *     exclude_beneficiary_id?: int|null
     * }
     */
    protected function duplicateSearchInput(): array
    {
        return [
            'type' => 'organization',
            'name' => $this->input('name'),
            'address_barangay_id' => $this->filled('address_barangay_id')
                ? $this->integer('address_barangay_id')
                : null,
        ];
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}

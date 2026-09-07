<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Concerns\ValidatesDuplicateBeneficiaries;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIndividualRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'birthday' => ['nullable', 'date'],
            'sex' => ['required', 'string', Rule::in(['Male', 'Female'])],
            'other_address' => ['nullable', 'string', 'max:500'],
            'civil_status_id' => ['nullable', 'integer', Rule::exists('civil_statuses', 'id')],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'indigenous' => ['nullable', 'boolean'],
            'ethnicity' => ['nullable', 'string', 'max:255'],
            'pwd' => ['nullable', 'boolean'],
            'is_4ps_beneficiary' => ['nullable', 'boolean'],
            'is_solo_parent' => ['nullable', 'boolean'],
            'spouse' => ['nullable', 'string', 'max:255'],
            'address_province_id' => ['required', 'integer', Rule::exists('address_provinces', 'id')],
            'address_city_id' => ['required', 'integer', Rule::exists('address_cities', 'id')],
            'address_barangay_id' => ['required', 'integer', Rule::exists('address_barangays', 'id')],
            'identifications' => ['nullable', 'array'],
            'identifications.*.identification_id' => ['required', 'integer', Rule::exists('identifications', 'id')],
            'identifications.*.number' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'address_province_id' => 'Province',
            'address_city_id' => 'City/Municipality',
            'address_barangay_id' => 'Barangay',
            'civil_status_id' => 'Civil Status',
            'pwd' => 'person with disability',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterDuplicateCheck($validator, $this->duplicateSearchInput());
    }

    /**
     * @return array{
     *     type: 'individual',
     *     first_name: string|null,
     *     last_name: string|null,
     *     birthday: string|null,
     *     address_barangay_id: int|null,
     *     identifications: list<array{identification_id?: int, number?: string}>,
     *     exclude_beneficiary_id?: int|null
     * }
     */
    protected function duplicateSearchInput(): array
    {
        return [
            'type' => 'individual',
            'first_name' => $this->input('first_name'),
            'last_name' => $this->input('last_name'),
            'birthday' => $this->input('birthday'),
            'address_barangay_id' => $this->filled('address_barangay_id')
                ? $this->integer('address_barangay_id')
                : null,
            'identifications' => is_array($this->input('identifications'))
                ? $this->input('identifications')
                : [],
        ];
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}

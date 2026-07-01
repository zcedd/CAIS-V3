<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndividualRequest extends FormRequest
{
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
            'address_barangay_id' => ['nullable', 'integer', Rule::exists('address_barangays', 'id')],
            'identifications' => ['nullable', 'array'],
            'identifications.*.identification_id' => ['required', 'integer', Rule::exists('identifications', 'id')],
            'identifications.*.number' => ['required', 'string', 'max:255'],
        ];
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}

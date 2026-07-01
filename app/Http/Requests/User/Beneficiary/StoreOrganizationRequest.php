<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'beneficiary_id' => ['required', 'integer', Rule::exists('individuals', 'id')],
            'addrs_brgy_id' => ['nullable', 'integer', Rule::exists('address_barangays', 'id')],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'total_member' => ['nullable', 'integer', 'min:0'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', Rule::exists('individuals', 'id')],
        ];
    }

    protected function userBelongsToDepartment(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && $this->user()?->department_id === $department->id;
    }
}

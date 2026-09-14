<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use App\Http\Requests\User\Concerns\ValidatesDuplicateBeneficiaries;
use App\Models\Beneficiary;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrganizationRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;
    use ValidatesDuplicateBeneficiaries;

    public function authorize(): bool
    {
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return false;
        }

        if ($beneficiary->beneficiable_type !== Organization::class) {
            return false;
        }

        return $this->canUpdateBeneficiary();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return (new StoreOrganizationRequest)->rules();
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
     *     exclude_beneficiary_id: int|null
     * }
     */
    protected function duplicateSearchInput(): array
    {
        $beneficiary = $this->route('beneficiary');

        return [
            'type' => 'organization',
            'name' => $this->input('name'),
            'address_barangay_id' => $this->filled('address_barangay_id')
                ? $this->integer('address_barangay_id')
                : null,
            'exclude_beneficiary_id' => $beneficiary instanceof Beneficiary
                ? $beneficiary->id
                : null,
        ];
    }
}

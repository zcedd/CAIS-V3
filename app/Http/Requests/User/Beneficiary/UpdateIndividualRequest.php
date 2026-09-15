<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use App\Http\Requests\User\Concerns\ValidatesDuplicateBeneficiaries;
use App\Models\Beneficiary;
use App\Models\Individual;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateIndividualRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;
    use ValidatesDuplicateBeneficiaries;

    public function authorize(): bool
    {
        $beneficiary = $this->route('beneficiary');

        if (! $beneficiary instanceof Beneficiary) {
            return false;
        }

        if ($beneficiary->beneficiable_type !== Individual::class) {
            return false;
        }

        return $this->canUpdateBeneficiary();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = (new StoreIndividualRequest)->rules();
        unset($rules['intake_method']);

        foreach (array_keys($rules) as $key) {
            if ($key === 'face_liveness_session_id' || str_starts_with($key, 'everify_')) {
                unset($rules[$key]);
            }
        }

        return $rules;
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
     *     exclude_beneficiary_id: int|null
     * }
     */
    protected function duplicateSearchInput(): array
    {
        $beneficiary = $this->route('beneficiary');

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
            'exclude_beneficiary_id' => $beneficiary instanceof Beneficiary
                ? $beneficiary->id
                : null,
        ];
    }
}

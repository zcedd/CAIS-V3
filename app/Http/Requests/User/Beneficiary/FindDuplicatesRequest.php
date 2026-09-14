<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FindDuplicatesRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;

    public function authorize(): bool
    {
        return $this->canViewAnyBeneficiaries();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'beneficiary_type' => ['required', 'in:individual,organization'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'address_barangay_id' => ['nullable', 'integer', Rule::exists('address_barangays', 'id')],
            'identifications' => ['nullable', 'array'],
            'identifications.*.identification_id' => ['nullable', 'integer'],
            'identifications.*.number' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'exclude_beneficiary_id' => ['nullable', 'integer', Rule::exists('beneficiaries', 'id')],
        ];
    }

    /**
     * @return array{
     *     type: 'individual'|'organization',
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     birthday?: string|null,
     *     address_barangay_id?: int|null,
     *     identifications?: list<array{identification_id?: int, number?: string}>,
     *     name?: string|null,
     *     exclude_beneficiary_id?: int|null
     * }
     */
    public function duplicateSearchInput(): array
    {
        $type = $this->string('beneficiary_type')->toString() === 'organization'
            ? 'organization'
            : 'individual';

        if ($type === 'organization') {
            return [
                'type' => 'organization',
                'name' => $this->input('name'),
                'address_barangay_id' => $this->filled('address_barangay_id')
                    ? $this->integer('address_barangay_id')
                    : null,
                'exclude_beneficiary_id' => $this->filled('exclude_beneficiary_id')
                    ? $this->integer('exclude_beneficiary_id')
                    : null,
            ];
        }

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
            'exclude_beneficiary_id' => $this->filled('exclude_beneficiary_id')
                ? $this->integer('exclude_beneficiary_id')
                : null,
        ];
    }
}

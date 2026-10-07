<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Enums\EverifyIntakeMethod;
use App\Enums\NameSuffix;
use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use App\Http\Requests\User\Concerns\ValidatesDuplicateBeneficiaries;
use App\Models\User;
use App\Services\Everify\EverifyVerificationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIndividualRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;
    use ValidatesDuplicateBeneficiaries;

    /**
     * @var list<string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function authorize(): bool
    {
        return $this->canCreateBeneficiary();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('intake_method')) {
            $this->merge([
                'intake_method' => EverifyIntakeMethod::Manual->value,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usingEverify = $this->input('intake_method') === EverifyIntakeMethod::Everify->value;

        return [
            ...$this->duplicateAcknowledgementRules(),
            'intake_method' => ['required', Rule::enum(EverifyIntakeMethod::class)],
            'everify_verification_token' => [
                Rule::requiredIf($usingEverify),
                'nullable',
                'string',
                'uuid',
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', Rule::enum(NameSuffix::class)],
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
            'intake_method' => 'registration method',
            'everify_verification_token' => 'eVerify verification',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterDuplicateCheck($validator, $this->duplicateSearchInput());

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('intake_method') !== EverifyIntakeMethod::Everify->value) {
                return;
            }

            $service = $this->container->make(EverifyVerificationService::class);

            if (! $service->isConfigured()) {
                $validator->errors()->add(
                    'intake_method',
                    'eVerify is not configured. Enter details manually.',
                );

                return;
            }

            $user = $this->user();
            $token = (string) $this->input('everify_verification_token');

            if (! $user instanceof User || $token === '' || ! $service->ticketBelongsTo($user, $token)) {
                $validator->errors()->add(
                    'everify_verification_token',
                    'Verify with PhilSys before saving.',
                );
            }
        });
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
}

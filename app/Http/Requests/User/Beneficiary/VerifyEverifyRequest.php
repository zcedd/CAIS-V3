<?php

namespace App\Http\Requests\User\Beneficiary;

use App\Enums\EverifyBiometricMethod;
use App\Enums\EverifyIdentityMethod;
use App\Http\Requests\User\Beneficiary\Concerns\AuthorizesDepartmentBeneficiary;
use App\Services\Everify\EverifyVerificationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VerifyEverifyRequest extends FormRequest
{
    use AuthorizesDepartmentBeneficiary;

    /**
     * @var list<string>
     */
    protected $dontFlash = [
        'everify_fingerprint',
        'everify_qr_value',
        'face_liveness_session_id',
    ];

    public function authorize(): bool
    {
        return $this->canCreateBeneficiary();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $identityMethod = EverifyIdentityMethod::tryFrom((string) $this->input('everify_identity_method'));
        $biometricMethod = EverifyBiometricMethod::tryFrom((string) $this->input('everify_biometric_method'));
        $usingQuery = $identityMethod === EverifyIdentityMethod::Query;
        $usingQrValue = $identityMethod?->usesQrValue() === true;
        $usingFace = $biometricMethod === EverifyBiometricMethod::Face;
        $usingFingerprint = $biometricMethod === EverifyBiometricMethod::Fingerprint;
        $availableBiometrics = $this->container
            ->make(EverifyVerificationService::class)
            ->availableBiometricMethodValues();

        return [
            'everify_identity_method' => ['required', Rule::enum(EverifyIdentityMethod::class)],
            'everify_biometric_method' => [
                'required',
                Rule::enum(EverifyBiometricMethod::class),
                Rule::in($availableBiometrics),
            ],
            'everify_qr_value' => [
                Rule::requiredIf($usingQrValue),
                'nullable',
                'string',
                'max:8192',
            ],
            'face_liveness_session_id' => [
                Rule::requiredIf($usingFace),
                'nullable',
                'string',
                'max:100',
            ],
            'everify_fingerprint' => [
                Rule::requiredIf($usingFingerprint),
                'nullable',
                'array',
            ],
            'everify_fingerprint.biometrics' => [
                Rule::requiredIf($usingFingerprint),
                'nullable',
                'array',
                'min:1',
                'max:4',
            ],
            'everify_fingerprint.biometrics.*.specVersion' => [
                Rule::requiredIf($usingFingerprint),
                'nullable',
                'string',
                'max:20',
            ],
            'everify_fingerprint.biometrics.*.data' => [
                Rule::requiredIf($usingFingerprint),
                'nullable',
                'string',
                'max:200000',
            ],
            'first_name' => [
                Rule::requiredIf($usingQuery),
                'nullable',
                'string',
                'max:255',
            ],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => [
                Rule::requiredIf($usingQuery),
                'nullable',
                'string',
                'max:255',
            ],
            'suffix' => ['nullable', 'string', 'max:50'],
            'birthday' => [
                Rule::requiredIf($usingQuery),
                'nullable',
                'date',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'everify_identity_method' => 'eVerify identity method',
            'everify_biometric_method' => 'eVerify biometric method',
            'everify_qr_value' => 'National ID, PCN, or QR value',
            'face_liveness_session_id' => 'face check',
            'everify_fingerprint' => 'fingerprint capture',
        ];
    }

    /**
     * @param  array-key|null  $key
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null || ! is_array($validated)) {
            return $validated;
        }

        $fingerprint = $this->input('everify_fingerprint');

        if (
            ($validated['everify_biometric_method'] ?? null) === EverifyBiometricMethod::Fingerprint->value
            && is_array($fingerprint)
        ) {
            $validated['everify_fingerprint'] = $fingerprint;
        }

        return $validated;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->container->make(EverifyVerificationService::class)->isConfigured()) {
                $validator->errors()->add(
                    'everify_identity_method',
                    'eVerify is not configured. Enter details manually.',
                );
            }
        });
    }
}

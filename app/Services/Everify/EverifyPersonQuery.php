<?php

namespace App\Services\Everify;

use App\Enums\EverifyBiometricMethod;
use App\Enums\EverifyIdentityMethod;

final readonly class EverifyPersonQuery
{
    /**
     * @param  array<string, mixed>|null  $fingerprint
     */
    public function __construct(
        public EverifyIdentityMethod $identityMethod,
        public EverifyBiometricMethod $biometricMethod,
        public ?string $firstName = null,
        public ?string $middleName = null,
        public ?string $lastName = null,
        public ?string $suffix = null,
        public ?string $birthDate = null,
        public ?string $qrValue = null,
        public ?string $faceLivenessSessionId = null,
        public ?array $fingerprint = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $identity = EverifyIdentityMethod::from((string) $validated['everify_identity_method']);
        $biometric = EverifyBiometricMethod::from((string) $validated['everify_biometric_method']);

        return new self(
            identityMethod: $identity,
            biometricMethod: $biometric,
            firstName: self::nullableString($validated['first_name'] ?? null),
            middleName: self::nullableString($validated['middle_name'] ?? null),
            lastName: self::nullableString($validated['last_name'] ?? null),
            suffix: self::nullableString($validated['suffix'] ?? null),
            birthDate: self::nullableString($validated['birthday'] ?? null),
            qrValue: self::nullableString($validated['everify_qr_value'] ?? null),
            faceLivenessSessionId: self::nullableString($validated['face_liveness_session_id'] ?? null),
            fingerprint: is_array($validated['everify_fingerprint'] ?? null)
                ? $validated['everify_fingerprint']
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequestPayload(): array
    {
        $payload = $this->identityMethod->usesQrValue()
            ? ['value' => (string) $this->qrValue]
            : array_filter([
                'first_name' => $this->firstName,
                'middle_name' => $this->middleName,
                'last_name' => $this->lastName,
                'suffix' => $this->suffix,
                'birth_date' => $this->birthDate,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($this->biometricMethod === EverifyBiometricMethod::Face) {
            $payload['face_liveness_session_id'] = $this->faceLivenessSessionId;
        }

        if ($this->biometricMethod === EverifyBiometricMethod::Fingerprint && $this->fingerprint !== null) {
            $payload['fingerprint'] = $this->fingerprint;
        }

        return $payload;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

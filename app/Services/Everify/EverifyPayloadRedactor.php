<?php

namespace App\Services\Everify;

class EverifyPayloadRedactor
{
    /**
     * @var list<string>
     */
    private const SECRET_KEYS = [
        'access_token',
        'authorization',
        'biometrics',
        'client_secret',
        'face_liveness_session_id',
        'face_url',
        'fingerprint',
        'photo',
        'session_id',
        'sessionkey',
        'thumbprint',
        'value',
    ];

    /**
     * @var list<string>
     */
    private const PII_KEYS = [
        'birth_date',
        'birthday',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
    ];

    public function redact(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        $redacted = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : $key;

            if (is_string($normalizedKey) && in_array($normalizedKey, self::SECRET_KEYS, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            if (is_string($normalizedKey) && in_array($normalizedKey, self::PII_KEYS, true)) {
                $redacted[$key] = $this->maskPersonalValue($value);

                continue;
            }

            $redacted[$key] = $this->redact($value);
        }

        return $redacted;
    }

    private function maskPersonalValue(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '[redacted]';
        }

        return mb_substr(trim($value), 0, 1).'***';
    }
}

<?php

use App\Services\Everify\EverifyPayloadRedactor;

test('it redacts secrets and personal details from eVerify payloads', function () {
    $redactor = new EverifyPayloadRedactor;

    $redacted = $redactor->redact([
        'client_id' => 'public-client-id',
        'client_secret' => 'super-secret',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birth_date' => '1989-09-12',
        'data' => [
            'access_token' => 'jwt-token',
            'verified' => true,
        ],
        'fingerprint' => ['biometrics' => ['raw']],
        'photo' => 'data:image/jpeg;base64,abc',
        'session_id' => 'a1b3fae6-af74-4896-bd58-32a81604de01',
        'value' => 'PHILID-QR-RAW-VALUE',
    ]);

    expect($redacted)->toMatchArray([
        'client_id' => 'public-client-id',
        'client_secret' => '[redacted]',
        'first_name' => 'J***',
        'last_name' => 'D***',
        'birth_date' => '1***',
        'fingerprint' => '[redacted]',
        'photo' => '[redacted]',
        'session_id' => '[redacted]',
        'value' => '[redacted]',
    ])
        ->and($redacted['data']['access_token'])->toBe('[redacted]')
        ->and($redacted['data']['verified'])->toBeTrue();
});

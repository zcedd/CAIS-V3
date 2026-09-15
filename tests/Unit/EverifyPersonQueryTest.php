<?php

use App\Enums\EverifyBiometricMethod;
use App\Enums\EverifyIdentityMethod;
use App\Services\Everify\EverifyPersonQuery;

test('query face payload uses name, birthday, and the liveness session', function () {
    $payload = EverifyPersonQuery::fromValidated([
        'everify_identity_method' => EverifyIdentityMethod::Query->value,
        'everify_biometric_method' => EverifyBiometricMethod::Face->value,
        'first_name' => 'Juan',
        'middle_name' => 'Dela',
        'last_name' => 'Cruz',
        'birthday' => '1990-01-01',
        'face_liveness_session_id' => 'a1b3fae6-af74-4896-bd58-32a81604de01',
    ])->toRequestPayload();

    expect($payload)->toBe([
        'first_name' => 'Juan',
        'middle_name' => 'Dela',
        'last_name' => 'Cruz',
        'birth_date' => '1990-01-01',
        'face_liveness_session_id' => 'a1b3fae6-af74-4896-bd58-32a81604de01',
    ]);
});

test('qr fingerprint payload uses the qr value and the whole capture object', function () {
    $fingerprint = [
        'biometrics' => [
            [
                'specVersion' => '0.9.5',
                'data' => 'ew0KImFsZyI6ICJSUzI1NiIsDQogInR5cC',
                'sessionKey' => 'llXvx-session-key',
            ],
        ],
    ];

    $payload = EverifyPersonQuery::fromValidated([
        'everify_identity_method' => EverifyIdentityMethod::Qr->value,
        'everify_biometric_method' => EverifyBiometricMethod::Fingerprint->value,
        'everify_qr_value' => 'PHILID-QR-RAW-VALUE',
        'everify_fingerprint' => $fingerprint,
    ])->toRequestPayload();

    expect($payload)->toBe([
        'value' => 'PHILID-QR-RAW-VALUE',
        'fingerprint' => $fingerprint,
    ]);
});

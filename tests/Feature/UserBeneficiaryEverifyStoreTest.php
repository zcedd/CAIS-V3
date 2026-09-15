<?php

use App\Enums\EverifyVerificationStatus;
use App\Models\Department;
use App\Models\EverifyLog;
use App\Models\Individual;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

function enableEverify(): void
{
    config([
        'services.everify.enabled' => true,
        'services.everify.base_url' => 'https://ws.everify.gov.ph/api/dev',
        'services.everify.client_id' => 'test-client-id',
        'services.everify.client_secret' => 'test-client-secret',
        'services.everify.public_key' => 'test-public-key',
        'services.everify.timeout' => 15,
        'services.everify.connect_timeout' => 5,
        'services.everify.token_cache_seconds' => 1500,
        'services.everify.biometrics' => ['face', 'fingerprint'],
    ]);
}

function disableEverify(): void
{
    config([
        'services.everify.enabled' => false,
        'services.everify.client_id' => null,
        'services.everify.client_secret' => null,
        'services.everify.public_key' => null,
    ]);
}

/**
 * @param  array<string, mixed>  $queryBody
 * @param  array<string, string>  $queryHeaders
 */
function fakeEverifyQuery(array $queryBody, int $status = 200, array $queryHeaders = []): void
{
    Http::preventStrayRequests();

    Http::fake([
        'https://ws.everify.gov.ph/api/dev/auth' => Http::response([
            'data' => [
                'access_token' => 'secret-token-value',
                'token_type' => 'Bearer',
                'expires_at' => (string) now()->addMinutes(30)->timestamp,
            ],
        ]),
        'https://ws.everify.gov.ph/api/dev/query/qr' => Http::response(
            $queryBody,
            $status,
            $queryHeaders,
        ),
        'https://ws.everify.gov.ph/api/dev/query' => Http::response(
            $queryBody,
            $status,
            $queryHeaders,
        ),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function everifyVerifyPayload(array $overrides = []): array
{
    return [
        'everify_identity_method' => 'query',
        'everify_biometric_method' => 'face',
        'face_liveness_session_id' => 'a1b3fae6-af74-4896-bd58-32a81604de01',
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'birthday' => '1990-01-01',
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function everifyStorePayload(int $barangayId, string $token, array $overrides = []): array
{
    return [
        'intake_method' => 'everify',
        'everify_verification_token' => $token,
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'sex' => 'Male',
        'birthday' => '1990-01-01',
        ...addressCascadePayload($barangayId),
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function verifyEverifyPerson(User $user, Department $department, array $payload = []): string
{
    $response = test()->actingAs($user)->postJson(route('user.beneficiaries.individuals.everify', [
        'department' => $department->slug,
    ]), everifyVerifyPayload($payload));

    $response->assertOk()->assertJsonPath('verified', true);

    $token = $response->json('verification_token');

    expect($token)->toBeString();

    return $token;
}

/**
 * @return array{biometrics: list<array<string, mixed>>}
 */
function everifyFingerprintCapture(): array
{
    return [
        'biometrics' => [
            [
                'specVersion' => '0.9.5',
                'data' => 'ew0KImFsZyI6ICJSUzI1NiIsDQogInR5cC',
                'hash' => 'FA05375A02727A56C231D99E4A3B61C8623DEB80E02BE108EF1D71ABD25B002F',
                'sessionKey' => 'llXvx-session-key',
                'error' => [
                    'errorCode' => '0',
                    'errorInfo' => 'Success',
                ],
                'thumbprint' => '15D6D4F39B8321E63484132058430B0DDCA591BC5EF9CF1FB380962CA1D98BA0',
            ],
        ],
    ];
}

test('create beneficiary page tells the client whether eVerify is configured', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    disableEverify();

    $this->actingAs($user)
        ->get(route('user.beneficiaries.create', [
            'department' => $department->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/beneficiaries/create')
            ->where('everify_enabled', false)
            ->where('everify_public_key', null)
            ->missing('everify_client_secret')
            ->missing('services.everify'));
});

test('create beneficiary page enables eVerify when credentials are configured', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    enableEverify();

    $this->actingAs($user)
        ->get(route('user.beneficiaries.create', [
            'department' => $department->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/beneficiaries/create')
            ->where('everify_enabled', true)
            ->where('everify_public_key', 'test-public-key')
            ->where('everify_biometrics', ['face', 'fingerprint'])
            ->missing('everify_client_id')
            ->missing('everify_client_secret'));
});

test('create beneficiary page only exposes configured eVerify biometrics', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    enableEverify();
    config(['services.everify.biometrics' => ['fingerprint']]);

    $this->actingAs($user)
        ->get(route('user.beneficiaries.create', [
            'department' => $department->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/beneficiaries/create')
            ->where('everify_biometrics', ['fingerprint']));
});

test('manual individual registration skips eVerify and does not call the api', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();

    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), [
        'intake_method' => 'manual',
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'sex' => 'Male',
        'birthday' => '1990-01-01',
        ...addressCascadePayload($barangayId),
    ])->assertRedirect();

    $individual = Individual::query()->where('first_name', 'Juan')->first();

    expect($individual)->not->toBeNull()
        ->and($individual->everify_status)->toBe(EverifyVerificationStatus::Skipped)
        ->and($individual->everify_verified_at)->toBeNull();

    Http::assertNothingSent();
    expect(EverifyLog::query()->count())->toBe(0);
});

test('everify registration creates a verified individual and stores redacted logs', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();
    enableEverify();
    fakeEverifyQuery([
        'data' => ['verified' => true],
        'meta' => ['tier_level' => 'Tier I', 'result_grade' => 1],
    ], queryHeaders: ['Query-Log-Id' => '01TESTLOG']);

    $token = verifyEverifyPerson($user, $department, [
        'middle_name' => 'Dela',
    ]);
    $sentCount = Http::recorded()->count();

    $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), everifyStorePayload($barangayId, $token, [
        'middle_name' => 'Dela',
    ]))->assertRedirect()
        ->assertSessionHas('success', 'Individual beneficiary created and verified with PhilSys.');

    $individual = Individual::query()->where('first_name', 'Juan')->first();

    expect($individual)->not->toBeNull()
        ->and($individual->everify_status)->toBe(EverifyVerificationStatus::Verified)
        ->and($individual->everify_result_grade)->toBe(1)
        ->and($individual->everify_query_log_id)->toBe('01TESTLOG')
        ->and($individual->everify_verified_at)->not->toBeNull();

    $authLog = EverifyLog::query()->where('purpose', 'auth')->first();
    $queryLog = EverifyLog::query()->where('purpose', 'query')->first();

    expect($authLog)->not->toBeNull()
        ->and($authLog->request['client_secret'])->toBe('[redacted]')
        ->and($authLog->response['data']['access_token'])->toBe('[redacted]')
        ->and($queryLog)->not->toBeNull()
        ->and($queryLog->individual_id)->toBe($individual->id)
        ->and($queryLog->request['first_name'])->toBe('J***')
        ->and($queryLog->request['last_name'])->toBe('C***')
        ->and($queryLog->request['birth_date'])->toBe('1***')
        ->and($queryLog->request['face_liveness_session_id'])->toBe('[redacted]')
        ->and($queryLog->query_log_id)->toBe('01TESTLOG');

    Http::assertSentCount($sentCount);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://ws.everify.gov.ph/api/dev/auth'
        && $request['client_id'] === 'test-client-id');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://ws.everify.gov.ph/api/dev/query'
        && $request['first_name'] === 'Juan'
        && $request['face_liveness_session_id'] === 'a1b3fae6-af74-4896-bd58-32a81604de01'
        && $request->hasHeader('Authorization', 'Bearer secret-token-value'));
});

test('failed eVerify does not create the individual', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    enableEverify();
    fakeEverifyQuery([
        'data' => ['verified' => false],
        'meta' => ['tier_level' => 'Tier I', 'result_grade' => 2],
    ]);

    $this->actingAs($user)->postJson(route('user.beneficiaries.individuals.everify', [
        'department' => $department->slug,
    ]), everifyVerifyPayload())->assertUnprocessable()
        ->assertJsonValidationErrors('intake_method');

    expect(Individual::query()->count())->toBe(0)
        ->and(EverifyLog::query()->where('purpose', 'query')->exists())->toBeTrue();
});

test('everify verification requires a birthday for name checks', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    enableEverify();
    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs($user)->postJson(route('user.beneficiaries.individuals.everify', [
        'department' => $department->slug,
    ]), everifyVerifyPayload([
        'birthday' => null,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('birthday');

    Http::assertNothingSent();
    expect(Individual::query()->count())->toBe(0);
});

test('everify verification is rejected when the service is not configured', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    disableEverify();
    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs($user)->postJson(route('user.beneficiaries.individuals.everify', [
        'department' => $department->slug,
    ]), everifyVerifyPayload())->assertUnprocessable()
        ->assertJsonValidationErrors('everify_identity_method');

    Http::assertNothingSent();
    expect(Individual::query()->count())->toBe(0);
});

test('unavailable eVerify does not create the individual', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    enableEverify();
    Http::preventStrayRequests();
    Http::fake([
        'https://ws.everify.gov.ph/api/dev/auth' => Http::failedConnection(),
    ]);

    $this->actingAs($user)->postJson(route('user.beneficiaries.individuals.everify', [
        'department' => $department->slug,
    ]), everifyVerifyPayload())->assertUnprocessable()
        ->assertJsonValidationErrors('intake_method');

    expect(Individual::query()->count())->toBe(0);
});

test('qr eVerify verification posts the qr value to query/qr', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();
    enableEverify();
    fakeEverifyQuery([
        'data' => ['verified' => true],
        'meta' => ['tier_level' => 'Tier I', 'result_grade' => 1],
    ]);

    $token = verifyEverifyPerson($user, $department, [
        'everify_identity_method' => 'qr',
        'everify_qr_value' => 'PHILID-QR-RAW-VALUE',
        'birthday' => null,
    ]);

    $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), everifyStorePayload($barangayId, $token, [
        'birthday' => null,
    ]))->assertRedirect()
        ->assertSessionHas('success', 'Individual beneficiary created and verified with PhilSys.');

    $queryLog = EverifyLog::query()->where('purpose', 'qr_query')->first();

    expect(Individual::query()->where('first_name', 'Juan')->exists())->toBeTrue()
        ->and($queryLog)->not->toBeNull()
        ->and($queryLog->request['value'])->toBe('[redacted]');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://ws.everify.gov.ph/api/dev/query/qr'
        && $request['value'] === 'PHILID-QR-RAW-VALUE'
        && $request['face_liveness_session_id'] === 'a1b3fae6-af74-4896-bd58-32a81604de01');
    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://ws.everify.gov.ph/api/dev/query');
});

test('pcn fingerprint eVerify sends the capture payload and does not store biometrics', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();
    enableEverify();
    fakeEverifyQuery([
        'data' => ['verified' => true],
        'meta' => ['tier_level' => 'Tier I', 'result_grade' => 1],
    ]);
    $fingerprint = everifyFingerprintCapture();

    $token = verifyEverifyPerson($user, $department, [
        'everify_identity_method' => 'pcn',
        'everify_biometric_method' => 'fingerprint',
        'everify_qr_value' => '1234-5678-9012-3456',
        'face_liveness_session_id' => null,
        'everify_fingerprint' => $fingerprint,
    ]);

    $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), everifyStorePayload($barangayId, $token))->assertRedirect();

    $individual = Individual::query()->where('first_name', 'Juan')->first();
    $queryLog = EverifyLog::query()->where('purpose', 'qr_query')->first();

    expect($individual)->not->toBeNull()
        ->and($individual->getAttributes())->not->toHaveKey('fingerprint')
        ->and(Schema::hasColumn('individuals', 'fingerprint'))->toBeFalse()
        ->and(Schema::hasColumn('individuals', 'face_liveness_session_id'))->toBeFalse()
        ->and($queryLog)->not->toBeNull()
        ->and($queryLog->request['fingerprint'])->toBe('[redacted]')
        ->and($queryLog->request['value'])->toBe('[redacted]');

    Http::assertSent(function ($request) use ($fingerprint): bool {
        return $request->url() === 'https://ws.everify.gov.ph/api/dev/query/qr'
            && $request['value'] === '1234-5678-9012-3456'
            && $request['fingerprint'] === $fingerprint;
    });
});

test('everify verification requires a biometric capture', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    enableEverify();
    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs($user)->postJson(route('user.beneficiaries.individuals.everify', [
        'department' => $department->slug,
    ]), everifyVerifyPayload([
        'face_liveness_session_id' => null,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('face_liveness_session_id');

    Http::assertNothingSent();
    expect(Individual::query()->count())->toBe(0);
});

test('everify verification rejects a biometric that is not configured', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    enableEverify();
    config(['services.everify.biometrics' => ['fingerprint']]);
    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs($user)->postJson(route('user.beneficiaries.individuals.everify', [
        'department' => $department->slug,
    ]), everifyVerifyPayload())->assertUnprocessable()
        ->assertJsonValidationErrors([
            'everify_biometric_method' => 'The selected eVerify biometric method is invalid.',
        ]);

    Http::assertNothingSent();
    expect(Individual::query()->count())->toBe(0);
});

test('everify store without a verification ticket is rejected', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();
    enableEverify();
    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), everifyStorePayload($barangayId, '11111111-1111-1111-1111-111111111111'))
        ->assertSessionHasErrors('everify_verification_token');

    Http::assertNothingSent();
    expect(Individual::query()->count())->toBe(0);
});

test('an eVerify ticket cannot be reused after saving', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();
    enableEverify();
    fakeEverifyQuery([
        'data' => ['verified' => true],
        'meta' => ['tier_level' => 'Tier I', 'result_grade' => 1],
    ]);

    $token = verifyEverifyPerson($user, $department);

    $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), everifyStorePayload($barangayId, $token))->assertRedirect();

    $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), everifyStorePayload($barangayId, $token, [
        'first_name' => 'Pedro',
        'duplicate_acknowledged' => true,
    ]))->assertSessionHasErrors('everify_verification_token');

    expect(Individual::query()->count())->toBe(1);
});

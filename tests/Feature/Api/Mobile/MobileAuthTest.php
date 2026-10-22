<?php

use App\Actions\Mobile\IssueMobileToken;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\mock;

test('login issues a bearer token limited to mobile distribution', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('api.mobile.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Field phone',
    ])->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.name', $user->name)
        ->assertJsonMissingPath('two_factor');

    $token = PersonalAccessToken::findToken($response->json('token'));

    expect($token?->abilities)->toBe([IssueMobileToken::ABILITY]);

    $this->withToken($response->json('token'))
        ->getJson(route('api.mobile.me'))
        ->assertOk();
});

test('returns 422 when the password does not match', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.mobile.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Field phone',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email' => 'These credentials do not match our records.',
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('returns 422 when login fields are missing', function () {
    $this->postJson(route('api.mobile.login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
            'device_name' => 'The device name field is required.',
        ]);
});

test('returns a challenge instead of a token when two factor is confirmed', function () {
    $user = mobileTwoFactorUser();

    $this->postJson(route('api.mobile.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Field phone',
    ])->assertOk()
        ->assertJsonPath('two_factor', true)
        ->assertJsonStructure(['challenge_token'])
        ->assertJsonMissingPath('token');

    expect($user->tokens()->count())->toBe(0);
});

test('issues a token when the two factor code is valid', function () {
    $user = mobileTwoFactorUser();
    $challenge = mobileChallengeToken($user);

    mock(TwoFactorAuthenticationProvider::class)
        ->shouldReceive('verify')
        ->once()
        ->with('test-secret', '123456')
        ->andReturn(true);

    $response = $this->postJson(route('api.mobile.two-factor'), [
        'challenge_token' => $challenge,
        'code' => '123456',
        'device_name' => 'Field phone',
    ])->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id);

    expect(PersonalAccessToken::findToken($response->json('token'))?->abilities)->toBe([IssueMobileToken::ABILITY]);

    $this->postJson(route('api.mobile.two-factor'), [
        'challenge_token' => $challenge,
        'code' => '123456',
        'device_name' => 'Field phone',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_token');
});

test('returns 422 when the two factor code is invalid', function () {
    $user = mobileTwoFactorUser();

    mock(TwoFactorAuthenticationProvider::class)
        ->shouldReceive('verify')
        ->once()
        ->with('test-secret', '000000')
        ->andReturn(false);

    $this->postJson(route('api.mobile.two-factor'), [
        'challenge_token' => mobileChallengeToken($user),
        'code' => '000000',
        'device_name' => 'Field phone',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'code' => 'The provided two factor authentication code was invalid.',
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('issues a token when a recovery code is valid and retires that code', function () {
    $user = mobileTwoFactorUser();

    mock(TwoFactorAuthenticationProvider::class)
        ->shouldReceive('verify')
        ->once()
        ->with('test-secret', 'code1')
        ->andReturn(false);

    $this->postJson(route('api.mobile.two-factor'), [
        'challenge_token' => mobileChallengeToken($user),
        'code' => 'code1',
        'device_name' => 'Field phone',
    ])->assertOk()
        ->assertJsonPath('token_type', 'Bearer');

    $codes = $user->refresh()->recoveryCodes();

    expect($codes)->toHaveCount(2)
        ->and(in_array('code1', $codes, true))->toBeFalse();
});

test('returns 422 when the challenge token is unknown', function () {
    $this->postJson(route('api.mobile.two-factor'), [
        'challenge_token' => 'missing-challenge',
        'code' => '123456',
        'device_name' => 'Field phone',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'challenge_token' => 'This sign-in challenge has expired. Sign in again.',
        ]);
});

test('issues a token when a two factor secret is not confirmed', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->postJson(route('api.mobile.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Field phone',
    ])->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonMissingPath('two_factor');
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $this->postJson(route('api.mobile.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Field phone',
    ])->assertOk()->json('token');

    $this->withToken($token)
        ->postJson(route('api.mobile.logout'))
        ->assertNoContent();

    expect($user->fresh()->tokens()->count())->toBe(0);

    // Sanctum caches the request guard user for this process. Forget it so the next request resolves the deleted token.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson(route('api.mobile.me'))
        ->assertUnauthorized();
});

test('me returns the user department and permissions', function () {
    $department = Department::create(['name' => 'Mobile Welfare '.uniqid()]);
    seedRolesAndPermissions();
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->syncRoles([RoleName::ReleasingOfficer->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $token = $user->createToken('Field phone', [IssueMobileToken::ABILITY])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson(route('api.mobile.me'))
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('department.id', $department->id)
        ->assertJsonPath('department.name', $department->name)
        ->assertJsonPath('department.slug', $department->slug);

    expect($response->json('permissions'))->toContain('distribution.execute');
});

test('me returns a null department when the user has none', function () {
    $user = User::factory()->create(['department_id' => null]);
    $token = $user->createToken('Field phone', [IssueMobileToken::ABILITY])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.mobile.me'))
        ->assertOk()
        ->assertJsonPath('department', null);
});

test('returns 401 when me is called without a token', function () {
    $this->getJson(route('api.mobile.me'))->assertUnauthorized();
});

function mobileTwoFactorUser(): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user->refresh();
}

function mobileChallengeToken(User $user): string
{
    return test()->postJson(route('api.mobile.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Field phone',
    ])->assertOk()->json('challenge_token');
}

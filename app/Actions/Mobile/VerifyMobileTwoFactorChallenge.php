<?php

namespace App\Actions\Mobile;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class VerifyMobileTwoFactorChallenge
{
    public function __construct(
        private IssueMobileToken $tokens,
        private TwoFactorAuthenticationProvider $provider,
    ) {}

    /**
     * @return array{token: string, token_type: string, user: User}
     */
    public function __invoke(string $challengeToken, string $code, string $deviceName): array
    {
        $userId = Cache::get($this->tokens->cacheKey($challengeToken));

        if (! is_numeric($userId)) {
            throw ValidationException::withMessages([
                'challenge_token' => 'This sign-in challenge has expired. Sign in again.',
            ]);
        }

        $user = User::query()->find((int) $userId);

        if (! $user instanceof User) {
            Cache::forget($this->tokens->cacheKey($challengeToken));

            throw ValidationException::withMessages([
                'challenge_token' => 'This sign-in challenge has expired. Sign in again.',
            ]);
        }

        $code = trim($code);

        if (! $this->codeIsValid($user, $code)) {
            throw ValidationException::withMessages([
                'code' => __('The provided two factor authentication code was invalid.'),
            ]);
        }

        Cache::forget($this->tokens->cacheKey($challengeToken));

        return $this->tokens->issueFor($user, $deviceName);
    }

    private function codeIsValid(User $user, string $code): bool
    {
        return $this->totpIsValid($user, $code) || $this->recoveryCodeIsValid($user, $code);
    }

    private function totpIsValid(User $user, string $code): bool
    {
        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            return false;
        }

        try {
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        } catch (DecryptException) {
            return false;
        }

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        return $this->provider->verify($secret, $code);
    }

    private function recoveryCodeIsValid(User $user, string $code): bool
    {
        if (! is_string($user->two_factor_recovery_codes) || $user->two_factor_recovery_codes === '') {
            return false;
        }

        try {
            $codes = $user->recoveryCodes();
        } catch (DecryptException) {
            return false;
        }

        if (! is_array($codes)) {
            return false;
        }

        $match = null;

        foreach ($codes as $recoveryCode) {
            if (is_string($recoveryCode) && hash_equals($recoveryCode, $code)) {
                $match = $recoveryCode;
                break;
            }
        }

        if (! is_string($match)) {
            return false;
        }

        $user->replaceRecoveryCode($match);

        return true;
    }
}

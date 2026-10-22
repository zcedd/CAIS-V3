<?php

namespace App\Actions\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueMobileToken
{
    public const ABILITY = 'mobile:distribution';

    /**
     * @return array{token: string, token_type: string, user: User}|array{two_factor: true, challenge_token: string}
     */
    public function __invoke(string $email, string $password, string $deviceName): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($user->two_factor_confirmed_at !== null) {
            $challengeToken = Str::random(80);
            Cache::put($this->cacheKey($challengeToken), $user->id, now()->addMinutes(5));

            return [
                'two_factor' => true,
                'challenge_token' => $challengeToken,
            ];
        }

        return $this->issueFor($user, $deviceName);
    }

    /**
     * @return array{token: string, token_type: string, user: User}
     */
    public function issueFor(User $user, string $deviceName): array
    {
        $token = $user->createToken($deviceName, [self::ABILITY]);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }

    public function cacheKey(string $challengeToken): string
    {
        return 'mobile-two-factor:'.$challengeToken;
    }
}

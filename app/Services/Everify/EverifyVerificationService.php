<?php

namespace App\Services\Everify;

use App\Enums\EverifyBiometricMethod;
use App\Enums\EverifyVerificationStatus;
use App\Exceptions\EverifyVerificationException;
use App\Models\EverifyLog;
use App\Models\Individual;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EverifyVerificationService
{
    private const QUERY_RATE_LIMIT = 10;

    private const TICKET_TTL_MINUTES = 15;

    public function __construct(
        private EverifyClient $client,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) config('services.everify.enabled')
            && filled(config('services.everify.client_id'))
            && filled(config('services.everify.client_secret'))
            && filled(config('services.everify.base_url'));
    }

    /**
     * @return list<EverifyBiometricMethod>
     */
    public function availableBiometricMethods(): array
    {
        $configured = [];
        $methods = config('services.everify.biometrics', EverifyBiometricMethod::values());

        if (! is_array($methods)) {
            return EverifyBiometricMethod::cases();
        }

        foreach ($methods as $method) {
            if (! is_string($method)) {
                continue;
            }

            $case = EverifyBiometricMethod::tryFrom(strtolower(trim($method)));

            if ($case instanceof EverifyBiometricMethod) {
                $configured[$case->value] = $case;
            }
        }

        return $configured === []
            ? EverifyBiometricMethod::cases()
            : array_values($configured);
    }

    /**
     * @return list<string>
     */
    public function availableBiometricMethodValues(): array
    {
        return array_map(
            static fn (EverifyBiometricMethod $method): string => $method->value,
            $this->availableBiometricMethods(),
        );
    }

    /**
     * @return array{
     *     enabled: bool,
     *     public_key: string|null,
     *     liveness_sdk_url: string,
     *     biometrics: list<string>,
     *     fingerprint: array{
     *         ports: list<int>,
     *         env: string,
     *         domain_uri: string,
     *         device_id: string|null
     *     }
     * }
     */
    public function frontendConfig(): array
    {
        $configured = $this->isConfigured();

        return [
            'enabled' => $configured,
            'public_key' => $configured && filled(config('services.everify.public_key'))
                ? (string) config('services.everify.public_key')
                : null,
            'liveness_sdk_url' => (string) config('services.everify.liveness_sdk_url'),
            'biometrics' => $this->availableBiometricMethodValues(),
            'fingerprint' => [
                'ports' => array_values(array_map(
                    static fn (mixed $port): int => (int) $port,
                    config('services.everify.fingerprint_ports', [4301, 4302]),
                )),
                'env' => (string) config('services.everify.fingerprint_env', 'Production'),
                'domain_uri' => (string) config('services.everify.fingerprint_domain_uri'),
                'device_id' => filled(config('services.everify.fingerprint_device_id'))
                    ? (string) config('services.everify.fingerprint_device_id')
                    : null,
            ],
        ];
    }

    public function verify(User $actor, EverifyPersonQuery $query): EverifyVerificationResult
    {
        if (! $this->isConfigured()) {
            throw new EverifyVerificationException(
                'eVerify is not configured. Enter details manually.',
            );
        }

        $rateLimitKey = 'everify-query:'.$actor->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::QUERY_RATE_LIMIT)) {
            throw new EverifyVerificationException(
                'Too many verification attempts. Wait a minute and try again.',
            );
        }

        RateLimiter::hit($rateLimitKey, 60);

        $payload = $query->toRequestPayload();
        $result = $query->identityMethod->usesQrValue()
            ? $this->client->queryQr($actor->id, $payload)
            : $this->client->query($actor->id, $payload);
        $verified = data_get($result->body, 'data.verified') === true;
        $resultGrade = data_get($result->body, 'meta.result_grade');

        if ($result->statusCode !== 200 || ! $verified) {
            throw new EverifyVerificationException(
                'PhilSys did not verify this person. Try the check again, or enter details manually.',
            );
        }

        return new EverifyVerificationResult(
            status: EverifyVerificationStatus::Verified,
            resultGrade: is_numeric($resultGrade) ? (int) $resultGrade : null,
            queryLogId: $result->queryLogId,
            logId: $result->logId,
            verifiedAt: now(),
        );
    }

    public function issueTicket(User $actor, EverifyVerificationResult $result): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->ticketCacheKey($token), [
            'user_id' => $actor->id,
            'status' => $result->status->value,
            'result_grade' => $result->resultGrade,
            'query_log_id' => $result->queryLogId,
            'log_id' => $result->logId,
            'verified_at' => $result->verifiedAt?->format(DATE_ATOM),
        ], now()->addMinutes(self::TICKET_TTL_MINUTES));

        return $token;
    }

    public function ticketBelongsTo(User $actor, string $token): bool
    {
        $payload = Cache::get($this->ticketCacheKey($token));

        return is_array($payload) && (int) ($payload['user_id'] ?? 0) === $actor->id;
    }

    public function consumeTicket(User $actor, string $token): EverifyVerificationResult
    {
        $payload = Cache::pull($this->ticketCacheKey($token));

        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== $actor->id) {
            throw new EverifyVerificationException(
                'PhilSys verification expired or is invalid. Verify again.',
            );
        }

        $verifiedAt = isset($payload['verified_at']) && is_string($payload['verified_at'])
            ? Carbon::parse($payload['verified_at'])
            : now();

        return new EverifyVerificationResult(
            status: EverifyVerificationStatus::from((string) $payload['status']),
            resultGrade: is_numeric($payload['result_grade'] ?? null) ? (int) $payload['result_grade'] : null,
            queryLogId: is_string($payload['query_log_id'] ?? null) ? $payload['query_log_id'] : null,
            logId: is_numeric($payload['log_id'] ?? null) ? (int) $payload['log_id'] : null,
            verifiedAt: $verifiedAt,
        );
    }

    public function attachIndividual(EverifyVerificationResult $verification, Individual $individual): void
    {
        if ($verification->logId === null) {
            return;
        }

        EverifyLog::query()
            ->whereKey($verification->logId)
            ->update(['individual_id' => $individual->id]);
    }

    private function ticketCacheKey(string $token): string
    {
        return 'everify.ticket.'.hash('sha256', $token);
    }
}

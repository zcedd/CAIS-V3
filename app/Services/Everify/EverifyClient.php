<?php

namespace App\Services\Everify;

use App\Enums\EverifyEndpoint;
use App\Exceptions\EverifyVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class EverifyClient
{
    public function __construct(
        private EverifyLogger $logger,
    ) {}

    public function accessToken(int $userId): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock($this->tokenLockKey(), 10)->block(5, function () use ($userId): string {
            $cached = Cache::get($this->tokenCacheKey());

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            return $this->authenticate($userId);
        });
    }

    public function forgetAccessToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function query(int $userId, array $payload): EverifyHttpResult
    {
        return $this->sendWithTokenRefresh(
            $userId,
            EverifyEndpoint::Query,
            '/query',
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queryQr(int $userId, array $payload): EverifyHttpResult
    {
        return $this->sendWithTokenRefresh(
            $userId,
            EverifyEndpoint::QrQuery,
            '/query/qr',
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWithTokenRefresh(
        int $userId,
        EverifyEndpoint $purpose,
        string $path,
        array $payload,
    ): EverifyHttpResult {
        $result = $this->send(
            $userId,
            $purpose,
            'POST',
            $path,
            $payload,
            $this->accessToken($userId),
        );

        if ($result->statusCode !== 401) {
            return $result;
        }

        $this->forgetAccessToken();

        return $this->send(
            $userId,
            $purpose,
            'POST',
            $path,
            $payload,
            $this->accessToken($userId),
        );
    }

    private function authenticate(int $userId): string
    {
        $result = $this->send($userId, EverifyEndpoint::Auth, 'POST', '/auth', [
            'client_id' => (string) config('services.everify.client_id'),
            'client_secret' => (string) config('services.everify.client_secret'),
        ], retryConnection: true);

        $token = data_get($result->body, 'data.access_token');

        if ($result->statusCode !== 200 || ! is_string($token) || $token === '') {
            throw new EverifyVerificationException(
                'eVerify is unavailable. Try again or enter details manually.',
            );
        }

        Cache::put($this->tokenCacheKey(), $token, $this->tokenTtl($result->body));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(
        int $userId,
        EverifyEndpoint $purpose,
        string $method,
        string $path,
        array $payload,
        ?string $token = null,
        bool $retryConnection = false,
    ): EverifyHttpResult {
        $started = microtime(true);
        $url = rtrim((string) config('services.everify.base_url'), '/').$path;

        try {
            $request = $this->http($token, $retryConnection);
            $response = strtoupper($method) === 'POST'
                ? $request->post($path, $payload)
                : $request->send($method, $path, ['json' => $payload]);
        } catch (ConnectionException) {
            $this->logger->record(
                $userId,
                $purpose,
                $method,
                $url,
                $payload,
                ['error' => 'connection_failed'],
                null,
                null,
                round((microtime(true) - $started) * 1000, 2),
            );

            throw new EverifyVerificationException(
                'eVerify is unavailable. Try again or enter details manually.',
            );
        }

        $result = EverifyHttpResult::fromResponse($url, $method, $response, $started);
        $log = $this->logger->record(
            $userId,
            $purpose,
            $method,
            $url,
            $payload,
            $result->body,
            $result->statusCode,
            $result->queryLogId,
            $result->durationMs,
        );

        return $result->withLogId($log->id);
    }

    private function http(?string $token, bool $retryConnection): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.everify.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.everify.connect_timeout'))
            ->timeout((int) config('services.everify.timeout'));

        if ($retryConnection) {
            $request = $request->retry(
                2,
                0,
                static fn (Throwable $exception): bool => $exception instanceof ConnectionException,
            );
        }

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function tokenTtl(array $body): int
    {
        $expiresAt = data_get($body, 'data.expires_at');
        $expiresAtTimestamp = is_numeric($expiresAt) ? (int) $expiresAt : null;

        if ($expiresAtTimestamp !== null && $expiresAtTimestamp > now()->timestamp) {
            return max(30, $expiresAtTimestamp - now()->timestamp - 60);
        }

        return max(30, (int) config('services.everify.token_cache_seconds'));
    }

    private function tokenCacheKey(): string
    {
        return 'everify.access_token.'.hash('sha256', (string) config('services.everify.client_id'));
    }

    private function tokenLockKey(): string
    {
        return $this->tokenCacheKey().'.lock';
    }
}

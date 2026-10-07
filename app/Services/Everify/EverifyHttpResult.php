<?php

namespace App\Services\Everify;

use Illuminate\Http\Client\Response;

final readonly class EverifyHttpResult
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public string $url,
        public string $method,
        public int $statusCode,
        public array $body,
        public ?string $queryLogId,
        public float $durationMs,
        public ?int $logId = null,
    ) {}

    public static function fromResponse(string $url, string $method, Response $response, float $started): self
    {
        $json = $response->json();

        return new self(
            url: $url,
            method: $method,
            statusCode: $response->status(),
            body: is_array($json) ? $json : [],
            queryLogId: $response->header('Query-Log-Id') ?: null,
            durationMs: round((microtime(true) - $started) * 1000, 2),
        );
    }

    public function withLogId(int $logId): self
    {
        return new self(
            url: $this->url,
            method: $this->method,
            statusCode: $this->statusCode,
            body: $this->body,
            queryLogId: $this->queryLogId,
            durationMs: $this->durationMs,
            logId: $logId,
        );
    }
}

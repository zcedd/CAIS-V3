<?php

namespace App\Services\Everify;

use App\Enums\EverifyEndpoint;
use App\Models\EverifyLog;

class EverifyLogger
{
    public function __construct(
        private EverifyPayloadRedactor $redactor,
    ) {}

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     */
    public function record(
        int $userId,
        EverifyEndpoint $purpose,
        string $method,
        string $url,
        array $request,
        array $response,
        ?int $statusCode,
        ?string $queryLogId,
        float $durationMs,
    ): EverifyLog {
        return EverifyLog::query()->create([
            'user_id' => $userId,
            'purpose' => $purpose->value,
            'method' => $method,
            'url' => $url,
            'request' => $this->redactor->redact($request),
            'response' => $this->redactor->redact($response),
            'status_code' => $statusCode,
            'query_log_id' => $queryLogId,
            'duration_ms' => $durationMs,
        ]);
    }
}

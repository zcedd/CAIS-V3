<?php

namespace App\Services\Everify;

use App\Enums\EverifyVerificationStatus;
use DateTimeInterface;

final readonly class EverifyVerificationResult
{
    public function __construct(
        public EverifyVerificationStatus $status,
        public ?int $resultGrade = null,
        public ?string $queryLogId = null,
        public ?int $logId = null,
        public ?DateTimeInterface $verifiedAt = null,
    ) {}

    public static function skipped(): self
    {
        return new self(status: EverifyVerificationStatus::Skipped);
    }

    /**
     * @return array{
     *     everify_status: string,
     *     everify_result_grade: int|null,
     *     everify_query_log_id: string|null,
     *     everify_verified_at: DateTimeInterface|null
     * }
     */
    public function individualAttributes(): array
    {
        return [
            'everify_status' => $this->status->value,
            'everify_result_grade' => $this->resultGrade,
            'everify_query_log_id' => $this->queryLogId,
            'everify_verified_at' => $this->verifiedAt,
        ];
    }
}

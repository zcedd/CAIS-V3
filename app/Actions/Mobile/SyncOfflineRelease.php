<?php

namespace App\Actions\Mobile;

use App\Actions\Distribution\FinalizeDistributionTransaction;
use App\Actions\Distribution\RecordDistributionRelease;
use App\Actions\Distribution\StartDistributionTransaction;
use App\Enums\DistributionEvidenceType;
use App\Enums\DistributionReleaseOutcome;
use App\Enums\DistributionSyncOperationStatus;
use App\Enums\DistributionSyncOperationType;
use App\Enums\DistributionTransactionStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\DistributionSyncOperation;
use App\Models\DistributionTransaction;
use App\Models\User;
use App\Services\Distribution\DistributionEvidenceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncOfflineRelease
{
    public function __construct(
        private StartDistributionTransaction $start,
        private FinalizeDistributionTransaction $finalize,
        private RecordDistributionRelease $recordRelease,
        private DistributionEvidenceService $evidence,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function release(
        DistributionBatch $batch,
        DistributionBatchItem $item,
        User $actor,
        array $data,
        ?UploadedFile $livePhoto,
        ?UploadedFile $signature,
    ): array {
        $clientOperationId = (string) $data['client_operation_id'];
        $replay = $this->replayIfApplied($clientOperationId, $batch, $item);

        if ($replay !== null) {
            return $replay;
        }

        try {
            return DB::transaction(function () use ($batch, $item, $actor, $data, $livePhoto, $signature, $clientOperationId): array {
                $locked = $this->lock($clientOperationId);

                if ($locked instanceof DistributionSyncOperation && $locked->status === DistributionSyncOperationStatus::Applied) {
                    return $this->replayOperation($locked, $batch, $item);
                }

                $transaction = ($this->start)($batch, $item, $actor, (string) $data['idempotency_key']);

                if ($transaction->status === DistributionTransactionStatus::Finalized) {
                    $result = $this->releaseResult($batch, $transaction, 'already_applied');
                    $this->settle($clientOperationId, $actor, $batch, $item, $transaction, DistributionSyncOperationType::Release, (string) $data['captured_at'], $result);

                    return $result;
                }

                $metadata = [
                    'captured_at' => $data['captured_at'],
                    'source' => 'mobile',
                ];

                if ($livePhoto instanceof UploadedFile) {
                    $this->evidence->store($transaction, $actor, $livePhoto, DistributionEvidenceType::LivePhoto, [
                        ...$metadata,
                        'capture_source' => 'camera',
                    ]);
                }

                if ($signature instanceof UploadedFile) {
                    $this->evidence->store($transaction, $actor, $signature, DistributionEvidenceType::Signature, $metadata);
                }

                $transaction = ($this->finalize)($batch, $transaction, $actor, $data);
                $result = $this->releaseResult($batch, $transaction, 'applied');
                $this->settle($clientOperationId, $actor, $batch, $item, $transaction, DistributionSyncOperationType::Release, (string) $data['captured_at'], $result);

                return $result;
            });
        } catch (ValidationException|InsufficientStockException $exception) {
            $this->recordConflict($clientOperationId, $actor, $batch, $item, DistributionSyncOperationType::Release, (string) $data['captured_at'], $exception);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function absent(DistributionBatch $batch, DistributionBatchItem $item, User $actor, array $data): array
    {
        $clientOperationId = (string) $data['client_operation_id'];
        $replay = $this->replayIfApplied($clientOperationId, $batch, $item);

        if ($replay !== null) {
            return $replay;
        }

        try {
            return DB::transaction(function () use ($batch, $item, $actor, $data, $clientOperationId): array {
                $locked = $this->lock($clientOperationId);

                if ($locked instanceof DistributionSyncOperation && $locked->status === DistributionSyncOperationStatus::Applied) {
                    return $this->replayOperation($locked, $batch, $item);
                }

                $record = ($this->recordRelease)($batch, $actor, [
                    'distribution_batch_item_id' => $item->id,
                    'outcome' => DistributionReleaseOutcome::Absent->value,
                    'remarks' => $data['remarks'] ?? null,
                    'released_at' => $data['captured_at'] ?? null,
                ]);

                $result = [
                    'status' => 'applied',
                    'distribution_release_id' => $record->id,
                ];

                $this->settle($clientOperationId, $actor, $batch, $item, null, DistributionSyncOperationType::Absent, (string) $data['captured_at'], $result);

                return $result;
            });
        } catch (ValidationException|InsufficientStockException $exception) {
            $this->recordConflict($clientOperationId, $actor, $batch, $item, DistributionSyncOperationType::Absent, (string) $data['captured_at'], $exception);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function replayIfApplied(string $clientOperationId, DistributionBatch $batch, DistributionBatchItem $item): ?array
    {
        $operation = DistributionSyncOperation::query()
            ->where('client_operation_id', $clientOperationId)
            ->first();

        if (! $operation instanceof DistributionSyncOperation || $operation->status !== DistributionSyncOperationStatus::Applied) {
            return null;
        }

        return $this->replayOperation($operation, $batch, $item);
    }

    /**
     * @return array<string, mixed>
     */
    private function replayOperation(DistributionSyncOperation $operation, DistributionBatch $batch, DistributionBatchItem $item): array
    {
        if ((int) $operation->distribution_batch_id !== (int) $batch->id || (int) $operation->distribution_batch_item_id !== (int) $item->id) {
            throw ValidationException::withMessages([
                'client_operation_id' => 'This offline operation is already tied to another beneficiary.',
            ]);
        }

        $result = $operation->result ?? [];
        $result['status'] = 'already_applied';

        return $result;
    }

    private function lock(string $clientOperationId): ?DistributionSyncOperation
    {
        $operation = DistributionSyncOperation::query()
            ->where('client_operation_id', $clientOperationId)
            ->lockForUpdate()
            ->first();

        return $operation instanceof DistributionSyncOperation ? $operation : null;
    }

    /**
     * @return array{status: string, transaction_number: string, receipt_number: string|null, receipt_pdf_url: string}
     */
    private function releaseResult(DistributionBatch $batch, DistributionTransaction $transaction, string $status): array
    {
        $transaction->loadMissing('currentReceipt');

        return [
            'status' => $status,
            'transaction_number' => $transaction->transaction_number,
            'receipt_number' => $transaction->currentReceipt?->receipt_number,
            'receipt_pdf_url' => route('api.mobile.distributions.transactions.receipt', [
                'distributionBatch' => $batch,
                'transaction' => $transaction,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function settle(
        string $clientOperationId,
        User $actor,
        DistributionBatch $batch,
        DistributionBatchItem $item,
        ?DistributionTransaction $transaction,
        DistributionSyncOperationType $type,
        string $capturedAt,
        array $result,
    ): void {
        DistributionSyncOperation::query()->updateOrCreate(
            ['client_operation_id' => $clientOperationId],
            [
                'user_id' => $actor->id,
                'distribution_batch_id' => $batch->id,
                'distribution_batch_item_id' => $item->id,
                'distribution_transaction_id' => $transaction?->id,
                'type' => $type,
                'status' => DistributionSyncOperationStatus::Applied,
                'device_captured_at' => $capturedAt,
                'result' => $result,
                'error' => null,
            ],
        );
    }

    private function recordConflict(
        string $clientOperationId,
        User $actor,
        DistributionBatch $batch,
        DistributionBatchItem $item,
        DistributionSyncOperationType $type,
        string $capturedAt,
        Throwable $exception,
    ): void {
        $existing = DistributionSyncOperation::query()
            ->where('client_operation_id', $clientOperationId)
            ->first();

        if ($existing?->status === DistributionSyncOperationStatus::Applied) {
            return;
        }

        DistributionSyncOperation::query()->updateOrCreate(
            ['client_operation_id' => $clientOperationId],
            [
                'user_id' => $actor->id,
                'distribution_batch_id' => $batch->id,
                'distribution_batch_item_id' => $item->id,
                'distribution_transaction_id' => null,
                'type' => $type,
                'status' => DistributionSyncOperationStatus::Conflict,
                'device_captured_at' => $capturedAt,
                'result' => null,
                'error' => $this->conflictError($exception),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function conflictError(Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            return [
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ];
        }

        if ($exception instanceof InsufficientStockException) {
            return [
                'message' => $exception->getMessage(),
                'errors' => [
                    $exception->field => [$exception->getMessage()],
                ],
            ];
        }

        return [
            'message' => $exception->getMessage(),
        ];
    }
}

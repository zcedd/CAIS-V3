<?php

namespace App\Services\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionSyncOperationStatus;
use App\Enums\DistributionSyncOperationType;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchAssignment;
use App\Models\DistributionBatchItem;
use App\Models\DistributionSyncOperation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;

class DistributionFieldSync
{
    public function __construct(private DistributionReconciliation $reconciliation) {}

    /**
     * Field-sync summary for a batch that has started and has an assignment or a received record.
     * Records still waiting on a phone are not on the server, so they are not included.
     *
     * @return array{
     *     applied_count: int,
     *     conflict_count: int,
     *     last_received_at: string|null,
     *     lag_warning_minutes: int,
     *     unserved_beneficiaries: int,
     *     unserved_with_conflict: int,
     *     officers: list<array{
     *         id: int,
     *         name: string,
     *         applied_count: int,
     *         conflict_count: int,
     *         last_received_at: string|null,
     *         worst_lag_seconds: int|null,
     *         lag_warning: bool
     *     }>,
     *     conflicts: list<array{
     *         id: int,
     *         beneficiary_name: string|null,
     *         cais_number: string|null,
     *         officer_name: string|null,
     *         type: string,
     *         type_label: string,
     *         device_captured_at: string|null,
     *         received_at: string|null,
     *         lag_seconds: int,
     *         lag_warning: bool,
     *         message: string|null
     *     }>
     * }|null
     */
    public function summarize(DistributionBatch $batch): ?array
    {
        if (! $this->hasStarted($batch)) {
            return null;
        }

        $operations = DistributionSyncOperation::query()
            ->where('distribution_batch_id', $batch->id)
            ->get([
                'id',
                'user_id',
                'distribution_batch_item_id',
                'type',
                'status',
                'device_captured_at',
                'updated_at',
            ]);

        $assignments = $batch->assignments()->get(['id', 'user_id']);

        if ($operations->isEmpty() && $assignments->isEmpty()) {
            return null;
        }

        $thresholdSeconds = $this->lagWarningMinutes() * 60;
        $officers = $this->officers($operations, $assignments, $thresholdSeconds);
        $conflicts = $this->conflicts($batch, $thresholdSeconds);
        $unservedItemIds = $this->reconciliation->unservedItemIds($batch);
        $conflictItemIds = $operations
            ->filter(fn (DistributionSyncOperation $operation): bool => $operation->status === DistributionSyncOperationStatus::Conflict)
            ->pluck('distribution_batch_item_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();

        $lastReceivedAt = $operations->max('updated_at');

        return [
            'applied_count' => $operations
                ->where('status', DistributionSyncOperationStatus::Applied)
                ->count(),
            'conflict_count' => $operations
                ->where('status', DistributionSyncOperationStatus::Conflict)
                ->count(),
            'last_received_at' => $lastReceivedAt instanceof DateTimeInterface
                ? $lastReceivedAt->toIso8601String()
                : null,
            'lag_warning_minutes' => $this->lagWarningMinutes(),
            'unserved_beneficiaries' => count($unservedItemIds),
            'unserved_with_conflict' => $conflictItemIds
                ->intersect($unservedItemIds)
                ->count(),
            'officers' => $officers,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Counts and open conflicts for the venue desk.
     *
     * @return array{
     *     applied_count: int,
     *     conflict_count: int,
     *     conflicts: list<array<string, mixed>>
     * }|null
     */
    public function alert(DistributionBatch $batch): ?array
    {
        $summary = $this->summarize($batch);

        if ($summary === null) {
            return null;
        }

        return [
            'applied_count' => $summary['applied_count'],
            'conflict_count' => $summary['conflict_count'],
            'conflicts' => $summary['conflicts'],
        ];
    }

    private function hasStarted(DistributionBatch $batch): bool
    {
        return $batch->status === DistributionBatchStatus::InProgress
            || $batch->status === DistributionBatchStatus::Completed;
    }

    private function lagWarningMinutes(): int
    {
        return max(0, (int) config('distribution.sync_lag_warning_minutes', 30));
    }

    /**
     * @param  Collection<int, DistributionSyncOperation>  $operations
     * @param  Collection<int, DistributionBatchAssignment>  $assignments
     * @return list<array{
     *     id: int,
     *     name: string,
     *     applied_count: int,
     *     conflict_count: int,
     *     last_received_at: string|null,
     *     worst_lag_seconds: int|null,
     *     lag_warning: bool
     * }>
     */
    private function officers(Collection $operations, Collection $assignments, int $thresholdSeconds): array
    {
        /** @var array<int, array{applied: int, conflict: int, last: DateTimeInterface|null, worst: int, has_record: bool}> $rows */
        $rows = [];

        foreach ($operations as $operation) {
            $userId = (int) $operation->user_id;
            $lag = $this->lagSeconds($operation->device_captured_at, $operation->updated_at);
            $row = $rows[$userId] ?? [
                'applied' => 0,
                'conflict' => 0,
                'last' => null,
                'worst' => 0,
                'has_record' => true,
            ];

            if ($operation->status === DistributionSyncOperationStatus::Applied) {
                $row['applied']++;
            }

            if ($operation->status === DistributionSyncOperationStatus::Conflict) {
                $row['conflict']++;
            }

            if ($row['last'] === null || ($operation->updated_at instanceof DateTimeInterface && $operation->updated_at > $row['last'])) {
                $row['last'] = $operation->updated_at;
            }

            $row['worst'] = max($row['worst'], $lag);
            $row['has_record'] = true;
            $rows[$userId] = $row;
        }

        foreach ($assignments as $assignment) {
            $userId = (int) $assignment->user_id;

            if (array_key_exists($userId, $rows)) {
                continue;
            }

            $rows[$userId] = [
                'applied' => 0,
                'conflict' => 0,
                'last' => null,
                'worst' => 0,
                'has_record' => false,
            ];
        }

        $users = User::query()
            ->whereIn('id', array_keys($rows))
            ->get(['id', 'firstName', 'middleName', 'lastName'])
            ->keyBy('id');

        $officers = [];

        foreach ($rows as $userId => $row) {
            $user = $users->get($userId);
            $last = $row['last'];

            $officers[] = [
                'id' => $userId,
                'name' => $user instanceof User ? $user->name : 'Unknown officer',
                'applied_count' => $row['applied'],
                'conflict_count' => $row['conflict'],
                'last_received_at' => $last instanceof DateTimeInterface ? $last->toIso8601String() : null,
                'worst_lag_seconds' => $row['has_record'] ? $row['worst'] : null,
                'lag_warning' => $row['has_record'] && $row['worst'] > $thresholdSeconds,
            ];
        }

        usort($officers, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        return $officers;
    }

    /**
     * @return list<array{
     *     id: int,
     *     beneficiary_name: string|null,
     *     cais_number: string|null,
     *     officer_name: string|null,
     *     type: string,
     *     type_label: string,
     *     device_captured_at: string|null,
     *     received_at: string|null,
     *     lag_seconds: int,
     *     lag_warning: bool,
     *     message: string|null
     * }>
     */
    private function conflicts(DistributionBatch $batch, int $thresholdSeconds): array
    {
        $operations = DistributionSyncOperation::query()
            ->where('distribution_batch_id', $batch->id)
            ->where('status', DistributionSyncOperationStatus::Conflict)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get([
                'id',
                'user_id',
                'distribution_batch_item_id',
                'type',
                'device_captured_at',
                'error',
                'updated_at',
            ]);

        if ($operations->isEmpty()) {
            return [];
        }

        $items = DistributionBatchItem::query()
            ->with('beneficiary:id,name,cais_number')
            ->whereIn('id', $operations->pluck('distribution_batch_item_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $users = User::query()
            ->whereIn('id', $operations->pluck('user_id')->unique()->all())
            ->get(['id', 'firstName', 'middleName', 'lastName'])
            ->keyBy('id');

        return $operations->map(function (DistributionSyncOperation $operation) use ($items, $users, $thresholdSeconds): array {
            $item = $items->get($operation->distribution_batch_item_id);
            $user = $users->get($operation->user_id);
            $lag = $this->lagSeconds($operation->device_captured_at, $operation->updated_at);
            $message = is_array($operation->error) ? ($operation->error['message'] ?? null) : null;

            return [
                'id' => $operation->id,
                'beneficiary_name' => $item instanceof DistributionBatchItem ? $item->beneficiary?->name : null,
                'cais_number' => $item instanceof DistributionBatchItem ? $item->beneficiary?->cais_number : null,
                'officer_name' => $user instanceof User ? $user->name : null,
                'type' => $operation->type->value,
                'type_label' => $this->typeLabel($operation->type),
                'device_captured_at' => $operation->device_captured_at?->toIso8601String(),
                'received_at' => $operation->updated_at?->toIso8601String(),
                'lag_seconds' => $lag,
                'lag_warning' => $lag > $thresholdSeconds,
                'message' => is_string($message) && $message !== '' ? $message : null,
            ];
        })->all();
    }

    private function lagSeconds(?DateTimeInterface $capturedAt, ?DateTimeInterface $receivedAt): int
    {
        if ($capturedAt === null || $receivedAt === null) {
            return 0;
        }

        return max(0, $receivedAt->getTimestamp() - $capturedAt->getTimestamp());
    }

    private function typeLabel(DistributionSyncOperationType $type): string
    {
        return match ($type) {
            DistributionSyncOperationType::Release => 'Release',
            DistributionSyncOperationType::Absent => 'Absent',
        };
    }
}

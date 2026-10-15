<?php

namespace App\Services\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionTransactionStatus;
use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\DistributionTransaction;
use Illuminate\Validation\ValidationException;

class AssistanceDistributionState
{
    /**
     * @return array{
     *     state: string,
     *     batch_id: int|null,
     *     batch_code: string|null,
     *     batch_status: string|null,
     *     batch_status_label: string|null,
     *     approval_mode: string|null,
     *     venue: string|null,
     *     scheduled_at: string|null,
     *     transaction_number: string|null,
     *     receipt_number: string|null,
     *     href: string|null,
     *     receipt_url: string|null
     * }
     */
    public function summarize(Department $department, Assistance $assistance): array
    {
        $active = $this->activeItem($assistance);
        $completed = $this->completedTransaction($assistance);
        $batch = $active?->batch ?? $completed?->batch;
        $transaction = $active?->transactions->first() ?? $completed;

        return [
            'state' => $active instanceof DistributionBatchItem
                ? 'active'
                : ($completed instanceof DistributionTransaction ? 'completed' : 'none'),
            'batch_id' => $batch?->id,
            'batch_code' => $batch?->batch_code,
            'batch_status' => $batch?->status?->value,
            'batch_status_label' => $batch?->status?->label(),
            'approval_mode' => $batch?->approval_mode?->value,
            'venue' => $batch?->venue,
            'scheduled_at' => $batch?->scheduled_at?->toIso8601String(),
            'transaction_number' => $transaction?->transaction_number,
            'receipt_number' => $completed?->currentReceipt?->receipt_number,
            'href' => $batch instanceof DistributionBatch ? $this->batchUrl($department, $batch, $active) : null,
            'receipt_url' => $completed instanceof DistributionTransaction && $completed->batch instanceof DistributionBatch
                ? route('user.distributions.transactions.receipt', [
                    'department' => $department,
                    'distributionBatch' => $completed->batch,
                    'transaction' => $completed,
                ])
                : null,
        ];
    }

    public function destination(Department $department, Assistance $assistance, string $mode): string
    {
        $active = $this->activeItem($assistance);

        if ($active instanceof DistributionBatchItem && $active->batch instanceof DistributionBatch) {
            return $this->batchUrl($department, $active->batch, $active);
        }

        $this->assertEligible($assistance);

        if ($mode === 'individual') {
            return route('user.distributions.individual', [
                'department' => $department,
                'program_id' => $assistance->program_id,
                'assistance_id' => $assistance->id,
            ]);
        }

        return route('user.distributions.create', [
            'department' => $department,
            'program_id' => $assistance->program_id,
            'assistance_id' => $assistance->id,
            'mode' => $mode,
        ]);
    }

    public function assertEligible(Assistance $assistance): void
    {
        if ($assistance->was_delivered) {
            throw ValidationException::withMessages([
                'assistance' => 'This request is already delivered.',
            ]);
        }

        $assistance->loadMissing('currentRequestSubStatus');

        foreach ([
            RequestSubStatusCode::Verified,
            RequestSubStatusCode::Approved,
            RequestSubStatusCode::ReadyForRelease,
            ...RequestSubStatusCode::distributionProgress(),
        ] as $code) {
            if ($code->matches($assistance->currentRequestSubStatus)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'assistance' => 'Only a verified, approved, or ready-for-release request can start a distribution.',
        ]);
    }

    private function activeItem(Assistance $assistance): ?DistributionBatchItem
    {
        return DistributionBatchItem::query()
            ->where('assistance_id', $assistance->id)
            ->whereHas('batch', function ($query): void {
                $query->whereIn('status', DistributionBatchStatus::blockingValues());
            })
            ->with([
                'batch',
                'transactions' => fn ($query) => $query->latest('id'),
            ])
            ->latest('id')
            ->first();
    }

    private function completedTransaction(Assistance $assistance): ?DistributionTransaction
    {
        return DistributionTransaction::query()
            ->where('status', DistributionTransactionStatus::Finalized)
            ->whereHas('batchItem', fn ($query) => $query->where('assistance_id', $assistance->id))
            ->with(['batch', 'currentReceipt'])
            ->latest('id')
            ->first();
    }

    private function batchUrl(Department $department, DistributionBatch $batch, ?DistributionBatchItem $item): string
    {
        if ($batch->status === DistributionBatchStatus::InProgress) {
            return route('user.distributions.venue', [
                'department' => $department,
                'distributionBatch' => $batch,
                'item' => $item?->id,
            ]);
        }

        if ($batch->isEditable()) {
            return route('user.distributions.edit', [
                'department' => $department,
                'distributionBatch' => $batch,
            ]);
        }

        return route('user.distributions.show', [
            'department' => $department,
            'distributionBatch' => $batch,
        ]);
    }
}

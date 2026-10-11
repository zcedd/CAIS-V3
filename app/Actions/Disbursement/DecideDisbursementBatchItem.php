<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementApprovalMode;
use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementDecisionAction;
use App\Enums\DisbursementItemApprovalStatus;
use App\Models\DisbursementApprovalDecision;
use App\Models\DisbursementApprovalPolicy;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementAuthorizer;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use App\Services\Disbursement\DisbursementNotifier;
use App\Services\Disbursement\DisbursementStageFlow;
use App\Services\Disbursement\DisbursementTotals;
use App\Support\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideDisbursementBatchItem
{
    public function __construct(
        private DisbursementBatchWorkflow $workflow,
        private DisbursementAuthorizer $authorizer,
        private DisbursementStageFlow $stages,
        private DisbursementTotals $totals,
        private DisbursementAudit $audit,
        private DisbursementNotifier $notifier,
    ) {}

    /**
     * @param  array{
     *     decision: string,
     *     remarks?: string|null,
     *     exclusion_reason?: string|null,
     *     lines?: list<array{id: int, approved_amount?: string|null, approved_quantity?: string|null}>,
     *     lock_version?: int|null
     * }  $data
     */
    public function __invoke(DisbursementBatch $batch, DisbursementBatchItem $item, User $actor, array $data): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $item, $actor, $data): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $item = DisbursementBatchItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);
            $this->guard($batch, $item);

            $decision = DisbursementDecisionAction::from($data['decision']);
            $remarks = trim((string) ($data['remarks'] ?? ''));
            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);
            $policy = $batch->policy;

            if (! $policy instanceof DisbursementApprovalPolicy) {
                throw ValidationException::withMessages([
                    'approval' => 'This disbursement batch has no approval policy.',
                ]);
            }

            $amounts = $this->applyLines($item, $decision, $data['lines'] ?? [], $actor, $policy, $remarks);

            if ($decision === DisbursementDecisionAction::Approved
                && $item->approval_status === DisbursementItemApprovalStatus::Approved
                && ! $amounts['changed']) {
                throw ValidationException::withMessages([
                    'decision' => 'This beneficiary is already approved for this revision.',
                ]);
            }

            $previous = $item->approval_status->value;

            if ($decision === DisbursementDecisionAction::Returned && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'A reason is required to return a beneficiary.',
                ]);
            }

            $exclusion = trim((string) ($data['exclusion_reason'] ?? $remarks));

            if ($decision === DisbursementDecisionAction::Excluded && $exclusion === '') {
                throw ValidationException::withMessages([
                    'exclusion_reason' => 'A reason is required to exclude a beneficiary.',
                ]);
            }

            if ($amounts['changed'] && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'Explain the amount or quantity change.',
                ]);
            }

            $storedDecision = $amounts['changed'] && $decision === DisbursementDecisionAction::Approved
                ? DisbursementDecisionAction::Modified
                : $decision;

            $item->forceFill([
                'approval_status' => match ($decision) {
                    DisbursementDecisionAction::Approved => DisbursementItemApprovalStatus::Approved,
                    DisbursementDecisionAction::Returned => DisbursementItemApprovalStatus::Returned,
                    DisbursementDecisionAction::Excluded => DisbursementItemApprovalStatus::Excluded,
                    default => $item->approval_status,
                },
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks !== '' ? $remarks : null,
                'exclusion_reason' => $decision === DisbursementDecisionAction::Excluded ? $exclusion : null,
            ])->save();

            DisbursementApprovalDecision::query()->create([
                'disbursement_batch_id' => $batch->id,
                'disbursement_batch_item_id' => $item->id,
                'disbursement_approval_task_id' => $task->id,
                'disbursement_approval_stage_id' => $task->disbursement_approval_stage_id,
                'revision' => $batch->revision,
                'actor_id' => $actor->id,
                'previous_decision' => $previous,
                'decision' => $storedDecision,
                'previous_amount' => $amounts['previous_amount'],
                'new_amount' => $amounts['new_amount'],
                'previous_quantity' => $amounts['previous_quantity'],
                'new_quantity' => $amounts['new_quantity'],
                'remarks' => $remarks !== '' ? $remarks : $exclusion,
            ]);

            $this->totals->sync($batch);
            $batch->lock_version = ((int) $batch->fresh()->lock_version) + 1;
            $batch->save();

            if ($decision === DisbursementDecisionAction::Returned) {
                $this->stages->returnBatch($batch->refresh(), $actor, $remarks);
            }

            $creator = $batch->creator;

            if ($creator instanceof User && in_array($decision, [DisbursementDecisionAction::Returned, DisbursementDecisionAction::Excluded], true)) {
                $this->notifier->send(
                    collect([$creator]),
                    $batch,
                    sprintf('disbursement:%d:item:%d:%s:%d', $batch->id, $item->id, $decision->value, $batch->revision),
                    $decision === DisbursementDecisionAction::Returned ? 'Beneficiary returned' : 'Beneficiary excluded',
                    sprintf('A beneficiary on %s was %s.', $batch->batch_code, $decision->value),
                );
            }

            $this->audit->record($actor, $batch, 'beneficiary_decided', [
                'revision' => $batch->revision,
                'disbursement_batch_item_id' => $item->id,
                'before' => ['approval_status' => $previous],
                'after' => [
                    'approval_status' => $item->approval_status->value,
                    'amount' => $amounts['new_amount'],
                    'quantity' => $amounts['new_quantity'],
                ],
            ]);

            return $batch->refresh();
        });
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function bulk(DisbursementBatch $batch, User $actor, array $itemIds, string $decision, ?string $remarks): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor, $itemIds, $decision, $remarks): DisbursementBatch {
            $updated = $batch;

            foreach (array_values(array_unique($itemIds)) as $itemId) {
                $item = DisbursementBatchItem::query()
                    ->where('disbursement_batch_id', $batch->id)
                    ->whereKey($itemId)
                    ->firstOrFail();

                $updated = $this($updated, $item, $actor, [
                    'decision' => $decision,
                    'remarks' => $remarks,
                    'exclusion_reason' => $decision === DisbursementDecisionAction::Excluded->value ? $remarks : null,
                ]);
            }

            return $updated;
        });
    }

    private function guard(DisbursementBatch $batch, DisbursementBatchItem $item): void
    {
        if ((int) $item->disbursement_batch_id !== (int) $batch->id) {
            throw ValidationException::withMessages([
                'item' => 'That beneficiary is not on this disbursement batch.',
            ]);
        }

        if ($batch->approval_mode !== DisbursementApprovalMode::Beneficiary) {
            throw ValidationException::withMessages([
                'decision' => 'This batch is approved as a whole. Review beneficiaries only when the approval mode is individual.',
            ]);
        }

        if ($batch->status !== DisbursementBatchStatus::ForApproval) {
            throw ValidationException::withMessages([
                'status' => 'Beneficiary decisions are available while the batch is waiting for approval.',
            ]);
        }
    }

    /**
     * @param  list<array{id: int, approved_amount?: string|null, approved_quantity?: string|null}>  $overrides
     * @return array{changed: bool, previous_amount: string, new_amount: string, previous_quantity: string, new_quantity: string}
     */
    private function applyLines(
        DisbursementBatchItem $item,
        DisbursementDecisionAction $decision,
        array $overrides,
        User $actor,
        DisbursementApprovalPolicy $policy,
        string $remarks,
    ): array {
        $item->load('lines');
        $previousAmount = '0.00';
        $newAmount = '0.00';
        $previousQuantity = '0.00';
        $newQuantity = '0.00';
        $changed = false;

        foreach ($item->lines as $line) {
            $previousAmount = DecimalAmount::add($previousAmount, $line->proposed_amount);
            $previousQuantity = DecimalAmount::add($previousQuantity, $line->proposed_quantity);

            $approvedAmount = $line->proposed_amount;
            $approvedQuantity = $line->proposed_quantity;
            $override = collect($overrides)->first(fn (array $row): bool => (int) $row['id'] === (int) $line->id);

            if (is_array($override) && $decision === DisbursementDecisionAction::Approved) {
                if (array_key_exists('approved_amount', $override) && $override['approved_amount'] !== null && $line->proposed_amount !== null) {
                    $approvedAmount = DecimalAmount::of($override['approved_amount']);
                }

                if (array_key_exists('approved_quantity', $override) && $override['approved_quantity'] !== null && $line->proposed_quantity !== null) {
                    $approvedQuantity = DecimalAmount::of($override['approved_quantity']);
                }
            }

            if ($decision === DisbursementDecisionAction::Excluded) {
                $approvedAmount = null;
                $approvedQuantity = null;
            }

            if ($approvedAmount !== null && DecimalAmount::comp($approvedAmount, '0') < 0) {
                throw ValidationException::withMessages([
                    'approved_amount' => 'Approved amounts cannot be negative.',
                ]);
            }

            if ($approvedQuantity !== null && DecimalAmount::comp($approvedQuantity, '0') < 0) {
                throw ValidationException::withMessages([
                    'approved_quantity' => 'Approved quantities cannot be negative.',
                ]);
            }

            $amountChanged = $line->proposed_amount !== null
                && $approvedAmount !== null
                && DecimalAmount::comp($approvedAmount, $line->proposed_amount) !== 0;
            $quantityChanged = $line->proposed_quantity !== null
                && $approvedQuantity !== null
                && DecimalAmount::comp($approvedQuantity, $line->proposed_quantity) !== 0;

            if ($amountChanged || $quantityChanged) {
                $changed = true;
            }

            if ($decision === DisbursementDecisionAction::Approved) {
                $line->forceFill([
                    'approved_amount' => $approvedAmount,
                    'approved_quantity' => $approvedQuantity,
                ])->save();
            }

            if ($decision === DisbursementDecisionAction::Excluded) {
                $line->forceFill([
                    'approved_amount' => null,
                    'approved_quantity' => null,
                ])->save();
            }

            $newAmount = DecimalAmount::add($newAmount, $approvedAmount);
            $newQuantity = DecimalAmount::add($newQuantity, $approvedQuantity);
        }

        if ($changed) {
            $this->authorizer->assertCanModifyAmounts($actor, $policy);

            if ($remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'Explain the amount or quantity change.',
                ]);
            }
        }

        return [
            'changed' => $changed,
            'previous_amount' => $previousAmount,
            'new_amount' => $newAmount,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
        ];
    }
}

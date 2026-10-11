<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalApprovalMode;
use App\Enums\DispersalBatchStatus;
use App\Enums\DispersalDecisionAction;
use App\Enums\DispersalItemApprovalStatus;
use App\Models\DispersalApprovalDecision;
use App\Models\DispersalApprovalPolicy;
use App\Models\DispersalBatch;
use App\Models\DispersalBatchItem;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalAuthorizer;
use App\Services\Dispersal\DispersalBatchWorkflow;
use App\Services\Dispersal\DispersalNotifier;
use App\Services\Dispersal\DispersalStageFlow;
use App\Services\Dispersal\DispersalTotals;
use App\Support\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideDispersalBatchItem
{
    public function __construct(
        private DispersalBatchWorkflow $workflow,
        private DispersalAuthorizer $authorizer,
        private DispersalStageFlow $stages,
        private DispersalTotals $totals,
        private DispersalAudit $audit,
        private DispersalNotifier $notifier,
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
    public function __invoke(DispersalBatch $batch, DispersalBatchItem $item, User $actor, array $data): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $item, $actor, $data): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $item = DispersalBatchItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);
            $this->guard($batch, $item);

            $decision = DispersalDecisionAction::from($data['decision']);
            $remarks = trim((string) ($data['remarks'] ?? ''));
            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);
            $policy = $batch->policy;

            if (! $policy instanceof DispersalApprovalPolicy) {
                throw ValidationException::withMessages([
                    'approval' => 'This dispersal batch has no approval policy.',
                ]);
            }

            $amounts = $this->applyLines($item, $decision, $data['lines'] ?? [], $actor, $policy, $remarks);

            if ($decision === DispersalDecisionAction::Approved
                && $item->approval_status === DispersalItemApprovalStatus::Approved
                && ! $amounts['changed']) {
                throw ValidationException::withMessages([
                    'decision' => 'This beneficiary is already approved for this revision.',
                ]);
            }

            $previous = $item->approval_status->value;

            if ($decision === DispersalDecisionAction::Returned && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'A reason is required to return a beneficiary.',
                ]);
            }

            $exclusion = trim((string) ($data['exclusion_reason'] ?? $remarks));

            if ($decision === DispersalDecisionAction::Excluded && $exclusion === '') {
                throw ValidationException::withMessages([
                    'exclusion_reason' => 'A reason is required to exclude a beneficiary.',
                ]);
            }

            if ($amounts['changed'] && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'Explain the amount or quantity change.',
                ]);
            }

            $storedDecision = $amounts['changed'] && $decision === DispersalDecisionAction::Approved
                ? DispersalDecisionAction::Modified
                : $decision;

            $item->forceFill([
                'approval_status' => match ($decision) {
                    DispersalDecisionAction::Approved => DispersalItemApprovalStatus::Approved,
                    DispersalDecisionAction::Returned => DispersalItemApprovalStatus::Returned,
                    DispersalDecisionAction::Excluded => DispersalItemApprovalStatus::Excluded,
                    default => $item->approval_status,
                },
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks !== '' ? $remarks : null,
                'exclusion_reason' => $decision === DispersalDecisionAction::Excluded ? $exclusion : null,
            ])->save();

            DispersalApprovalDecision::query()->create([
                'dispersal_batch_id' => $batch->id,
                'dispersal_batch_item_id' => $item->id,
                'dispersal_approval_task_id' => $task->id,
                'dispersal_approval_stage_id' => $task->dispersal_approval_stage_id,
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

            if ($decision === DispersalDecisionAction::Returned) {
                $this->stages->returnBatch($batch->refresh(), $actor, $remarks);
            }

            $creator = $batch->creator;

            if ($creator instanceof User && in_array($decision, [DispersalDecisionAction::Returned, DispersalDecisionAction::Excluded], true)) {
                $this->notifier->send(
                    collect([$creator]),
                    $batch,
                    sprintf('dispersal:%d:item:%d:%s:%d', $batch->id, $item->id, $decision->value, $batch->revision),
                    $decision === DispersalDecisionAction::Returned ? 'Beneficiary returned' : 'Beneficiary excluded',
                    sprintf('A beneficiary on %s was %s.', $batch->batch_code, $decision->value),
                );
            }

            $this->audit->record($actor, $batch, 'beneficiary_decided', [
                'revision' => $batch->revision,
                'dispersal_batch_item_id' => $item->id,
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
    public function bulk(DispersalBatch $batch, User $actor, array $itemIds, string $decision, ?string $remarks): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor, $itemIds, $decision, $remarks): DispersalBatch {
            $updated = $batch;

            foreach (array_values(array_unique($itemIds)) as $itemId) {
                $item = DispersalBatchItem::query()
                    ->where('dispersal_batch_id', $batch->id)
                    ->whereKey($itemId)
                    ->firstOrFail();

                $updated = $this($updated, $item, $actor, [
                    'decision' => $decision,
                    'remarks' => $remarks,
                    'exclusion_reason' => $decision === DispersalDecisionAction::Excluded->value ? $remarks : null,
                ]);
            }

            return $updated;
        });
    }

    private function guard(DispersalBatch $batch, DispersalBatchItem $item): void
    {
        if ((int) $item->dispersal_batch_id !== (int) $batch->id) {
            throw ValidationException::withMessages([
                'item' => 'That beneficiary is not on this dispersal batch.',
            ]);
        }

        if ($batch->approval_mode !== DispersalApprovalMode::Beneficiary) {
            throw ValidationException::withMessages([
                'decision' => 'This batch is approved as a whole. Review beneficiaries only when the approval mode is individual.',
            ]);
        }

        if ($batch->status !== DispersalBatchStatus::ForApproval) {
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
        DispersalBatchItem $item,
        DispersalDecisionAction $decision,
        array $overrides,
        User $actor,
        DispersalApprovalPolicy $policy,
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

            if (is_array($override) && $decision === DispersalDecisionAction::Approved) {
                if (array_key_exists('approved_amount', $override) && $override['approved_amount'] !== null && $line->proposed_amount !== null) {
                    $approvedAmount = DecimalAmount::of($override['approved_amount']);
                }

                if (array_key_exists('approved_quantity', $override) && $override['approved_quantity'] !== null && $line->proposed_quantity !== null) {
                    $approvedQuantity = DecimalAmount::of($override['approved_quantity']);
                }
            }

            if ($decision === DispersalDecisionAction::Excluded) {
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

            if ($decision === DispersalDecisionAction::Approved) {
                $line->forceFill([
                    'approved_amount' => $approvedAmount,
                    'approved_quantity' => $approvedQuantity,
                ])->save();
            }

            if ($decision === DispersalDecisionAction::Excluded) {
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

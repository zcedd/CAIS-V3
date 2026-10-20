<?php

namespace App\Services\Mobile;

use App\Enums\DistributionItemApprovalStatus;
use App\Enums\DistributionReleaseOutcome;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\User;
use App\Support\DecimalAmount;

class DistributionOfflinePackage
{
    /**
     * @return array<string, mixed>
     */
    public function build(DistributionBatch $batch, User $user): array
    {
        $batch->loadMissing('program:id,name');

        $items = DistributionBatchItem::query()
            ->where('distribution_batch_id', $batch->id)
            ->where('approval_status', DistributionItemApprovalStatus::Approved)
            ->with([
                'beneficiary:id,name,cais_number',
                'releases:id,distribution_batch_item_id,outcome',
                'lines.item:id,name,kind,item_unit_measurement_id',
                'lines.item.unitMeasurement:id,name',
                'lines.releases',
            ])
            ->orderBy('id')
            ->get();

        return [
            'batch' => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'title' => $batch->title,
                'status' => $batch->status->value,
                'status_label' => $batch->status->label(),
                'program' => $batch->program?->name,
                'department_id' => $batch->department_id,
                'venue' => $batch->venue,
                'venue_details' => $batch->venue_details,
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
            ],
            'items' => $items->map(fn (DistributionBatchItem $item): array => [
                'id' => $item->id,
                'assistance_id' => $item->assistance_id,
                'name' => $item->beneficiary?->name,
                'cais_number' => $item->beneficiary?->cais_number,
                'approval_status' => $item->approval_status->value,
                'release_status' => $this->releaseStatus($item),
                'lines' => $this->lines($item),
            ])->all(),
            'officers' => $this->officers((int) $batch->department_id),
            'can' => [
                'execute' => $user->can('execute', $batch),
                'waive' => $user->can('waiveLivePhoto', $batch),
                'issueReceipt' => $user->can('issueReceipt', $batch),
            ],
            'privacy_notice' => $this->privacyNotice(),
            'retention_notice' => $this->retentionNotice(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    public function releaseStatus(DistributionBatchItem $item): string
    {
        if ($item->approval_status === DistributionItemApprovalStatus::Excluded) {
            return 'excluded';
        }

        if ($item->releases->contains(fn ($release): bool => $release->outcome === DistributionReleaseOutcome::Absent)) {
            return 'absent';
        }

        if ($item->releases->contains(fn ($release): bool => $release->outcome === DistributionReleaseOutcome::Served)) {
            return 'served';
        }

        if ($item->releases->isNotEmpty()) {
            return 'partial';
        }

        return 'pending';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(DistributionBatchItem $item): array
    {
        return $item->lines->map(function ($line): array {
            $releasedQuantity = DecimalAmount::of((string) $line->releases->sum('actual_quantity'));
            $releasedAmount = DecimalAmount::of((string) $line->releases->sum('actual_amount'));

            return [
                'id' => $line->id,
                'name' => $line->item?->name,
                'kind' => $line->item?->kind?->value,
                'unit' => $line->item?->kind?->isCash() ? null : $line->item?->unitMeasurement?->name,
                'approved_quantity' => $line->approved_quantity,
                'approved_amount' => $line->approved_amount,
                'released_quantity' => $releasedQuantity,
                'released_amount' => $releasedAmount,
                'remaining_quantity' => $line->approved_quantity === null ? null : DecimalAmount::sub($line->approved_quantity, $releasedQuantity),
                'remaining_amount' => $line->approved_amount === null ? null : DecimalAmount::sub($line->approved_amount, $releasedAmount),
            ];
        })->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function officers(int $departmentId): array
    {
        return User::query()
            ->where('department_id', $departmentId)
            ->orderBy('lastName')
            ->limit(100)
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    public function privacyNotice(): string
    {
        return 'Personal information, a live photograph, and a signature or alternative acknowledgment are collected to document the release of government assistance. The lawful basis is the agency\'s delivery of an approved assistance program and the Data Privacy Act of 2012. A camera image, timestamp, or file hash does not by itself prove identity or that the recipient was present.';
    }

    public function retentionNotice(): string
    {
        return (string) config('distribution.evidence_retention');
    }
}

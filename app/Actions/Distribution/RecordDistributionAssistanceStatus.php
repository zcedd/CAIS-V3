<?php

namespace App\Actions\Distribution;

use App\Actions\User\RecalculateAssistanceSla;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionItemApprovalStatus;
use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\DistributionBatch;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Services\Workflow\RequestStatusCatalog;
use App\Services\Workflow\WorkflowEngine;

class RecordDistributionAssistanceStatus
{
    public function __construct(
        private RequestStatusCatalog $catalog,
        private WorkflowEngine $workflow,
        private RecalculateAssistanceSla $recalculateAssistanceSla,
    ) {}

    public function reflect(DistributionBatch $batch, User $actor): void
    {
        $batch->load(['items.assistance.currentRequestSubStatus']);
        $status = $batch->status;

        if (! $status instanceof DistributionBatchStatus) {
            return;
        }

        foreach ($batch->items as $item) {
            $assistance = $item->assistance;

            if (! $assistance instanceof Assistance) {
                continue;
            }

            if ($this->leavesUnapprovedBehind($status) && $item->approval_status !== DistributionItemApprovalStatus::Approved) {
                $this->restore($assistance, $actor, 'This beneficiary was not included in the approved distribution.');

                continue;
            }

            if ($item->approval_status === DistributionItemApprovalStatus::Excluded && ! $this->onDistributionStatus($assistance)) {
                continue;
            }

            $mapped = $this->mapping($status);

            if ($mapped === null) {
                continue;
            }

            $this->move(
                $assistance,
                $mapped['code'],
                $actor,
                $mapped['remark'],
                $mapped['finish_task'],
                $mapped['in_progress'],
            );
        }
    }

    public function recordRelease(Assistance $assistance, User $actor): void
    {
        $open = $assistance->assistanceItem()
            ->where('is_received', false)
            ->whereNull('substituted_at')
            ->exists();

        $this->move(
            $assistance,
            $open ? RequestSubStatusCode::PartiallyDelivered : RequestSubStatusCode::Delivered,
            $actor,
            $open
                ? 'Part of the assistance was released during distribution.'
                : 'Assistance was released during distribution.',
            ! $open,
            $open,
        );
    }

    public function recordAbsent(Assistance $assistance, User $actor, string $reason): void
    {
        $this->move(
            $assistance,
            RequestSubStatusCode::Absent,
            $actor,
            'The beneficiary was absent from distribution. '.$reason,
        );
    }

    /**
     * @return array{code: RequestSubStatusCode, remark: string, in_progress: bool, finish_task: bool}
     */
    private function mapping(DistributionBatchStatus $status): ?array
    {
        return match ($status) {
            DistributionBatchStatus::Draft => [
                'code' => RequestSubStatusCode::DistributionDraft,
                'remark' => 'The assistance request is on a draft distribution.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::ForValidation => [
                'code' => RequestSubStatusCode::ForDistributionValidation,
                'remark' => 'The assistance request is in distribution validation.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::ForApproval => [
                'code' => RequestSubStatusCode::ForDistributionApproval,
                'remark' => 'The assistance request is waiting for distribution approval.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::Returned => [
                'code' => RequestSubStatusCode::DistributionReturned,
                'remark' => 'The distribution was returned for correction.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::Rejected => [
                'code' => RequestSubStatusCode::DistributionRejected,
                'remark' => 'The distribution was rejected.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::Approved => [
                'code' => RequestSubStatusCode::ReadyForRelease,
                'remark' => 'Distribution approved. Ready for release.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::Scheduled => [
                'code' => RequestSubStatusCode::ScheduledForRelease,
                'remark' => 'The assistance request is scheduled for release.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::InProgress => [
                'code' => RequestSubStatusCode::ReleaseInProgress,
                'remark' => 'Release is in progress.',
                'in_progress' => true,
                'finish_task' => false,
            ],
            DistributionBatchStatus::Cancelled => [
                'code' => RequestSubStatusCode::DistributionCancelled,
                'remark' => 'The distribution was cancelled.',
                'in_progress' => false,
                'finish_task' => false,
            ],
            DistributionBatchStatus::Completed => [
                'code' => RequestSubStatusCode::Closed,
                'remark' => 'The distribution was closed.',
                'in_progress' => false,
                'finish_task' => true,
            ],
        };
    }

    private function leavesUnapprovedBehind(DistributionBatchStatus $status): bool
    {
        return in_array($status, [
            DistributionBatchStatus::Approved,
            DistributionBatchStatus::Scheduled,
            DistributionBatchStatus::InProgress,
            DistributionBatchStatus::Completed,
        ], true);
    }

    private function onDistributionStatus(Assistance $assistance): bool
    {
        $assistance->loadMissing('currentRequestSubStatus');
        $code = $assistance->currentRequestSubStatus?->code;

        return $code instanceof RequestSubStatusCode && $code->isDistributionProgress();
    }

    private function restore(Assistance $assistance, User $actor, string $remark): void
    {
        if (! $this->onDistributionStatus($assistance)) {
            return;
        }

        $priorId = AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->whereHas('requestSubStatus', function ($query): void {
                $query->where(function ($statusQuery): void {
                    $statusQuery->whereNotIn('code', RequestSubStatusCode::distributionProgressValues())
                        ->orWhereNull('code');
                });
            })
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('request_sub_status_id');

        $target = RequestSubStatus::query()->find($priorId)
            ?? RequestSubStatus::query()->findOrFail($this->catalog->reasonId(RequestSubStatusCode::Verified));

        $updated = $this->workflow->recordDistributionStatus($assistance, $target, $actor, $remark);
        ($this->recalculateAssistanceSla)($updated);
    }

    private function move(
        Assistance $assistance,
        RequestSubStatusCode $target,
        User $actor,
        string $remark,
        bool $finishTask = false,
        bool $markInProgress = false,
    ): void {
        $subStatus = RequestSubStatus::query()->findOrFail($this->catalog->reasonId($target));
        $updated = $this->workflow->recordDistributionStatus(
            $assistance,
            $subStatus,
            $actor,
            $remark,
            $finishTask,
            $markInProgress,
        );
        ($this->recalculateAssistanceSla)($updated);
    }
}

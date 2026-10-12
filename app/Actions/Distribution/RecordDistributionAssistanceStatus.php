<?php

namespace App\Actions\Distribution;

use App\Actions\User\RecalculateAssistanceSla;
use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
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

    public function recordApproval(Assistance $assistance, User $actor): void
    {
        $this->move(
            $assistance,
            RequestSubStatusCode::ReadyForRelease,
            $actor,
            'Distribution approved. Ready for release.',
        );
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
        );
    }

    private function move(Assistance $assistance, RequestSubStatusCode $target, User $actor, string $remark): void
    {
        $assistance->loadMissing('currentRequestSubStatus');
        $current = $assistance->currentRequestSubStatus?->code;

        if ($current === $target) {
            return;
        }

        if ($target === RequestSubStatusCode::ReadyForRelease
            && $current instanceof RequestSubStatusCode
            && $current->parent()->isTerminal()) {
            return;
        }

        $subStatus = RequestSubStatus::query()->findOrFail($this->catalog->reasonId($target));
        $updated = $this->workflow->recordStatusChange($assistance, $subStatus, $actor, $remark);
        ($this->recalculateAssistanceSla)($updated);
    }
}

<?php

namespace App\Actions\User;

use App\Enums\RequestStatusCode;
use App\Models\Assistance;
use App\Models\RequestStatus;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Support\Carbon;

class RecalculateAssistanceSla
{
    public function __invoke(Assistance $assistance, ?WorkflowStep $step = null): void
    {
        $assistance->loadMissing(['currentRequestSubStatus.requestStatus', 'program']);

        $status = $assistance->currentRequestSubStatus?->requestStatus;

        if (! $status instanceof RequestStatus) {
            $assistance->forceFill([
                'sla_due_at' => null,
                'sla_paused_at' => null,
            ])->save();

            return;
        }

        if (RequestStatusCode::OnHold->matches($status)) {
            if ($assistance->sla_paused_at === null) {
                $assistance->forceFill([
                    'sla_paused_at' => now(),
                ])->save();
            }

            return;
        }

        if ($this->isTerminal($status)) {
            $assistance->forceFill([
                'sla_due_at' => null,
                'sla_paused_at' => null,
            ])->save();

            return;
        }

        $step ??= $this->stepFor($assistance, (int) $status->id);

        if ($assistance->sla_paused_at !== null && $assistance->sla_due_at !== null) {
            $pausedFor = $assistance->sla_paused_at->diffInSeconds(now());
            $assistance->forceFill([
                'sla_due_at' => Carbon::parse($assistance->sla_due_at)->addSeconds($pausedFor),
                'sla_paused_at' => null,
            ])->save();

            return;
        }

        $hours = $step?->sla_hours;

        if ($hours === null || $hours <= 0) {
            $assistance->forceFill([
                'sla_due_at' => null,
                'sla_paused_at' => null,
            ])->save();

            return;
        }

        $startedAt = $assistance->current_status_recorded_at ?? now();

        $assistance->forceFill([
            'sla_due_at' => Carbon::parse($startedAt)->addHours($hours),
            'sla_paused_at' => null,
        ])->save();
    }

    private function isTerminal(RequestStatus $status): bool
    {
        return RequestStatusCode::matchesAny($status, [
            RequestStatusCode::Delivered,
            RequestStatusCode::Denied,
            RequestStatusCode::Closed,
        ]);
    }

    private function stepFor(Assistance $assistance, int $requestStatusId): ?WorkflowStep
    {
        $program = $assistance->program;

        if ($program === null) {
            return null;
        }

        $workflow = $program->resolvedWorkflow();

        return $workflow instanceof Workflow
            ? $workflow->stepForStatus($requestStatusId)
            : null;
    }
}

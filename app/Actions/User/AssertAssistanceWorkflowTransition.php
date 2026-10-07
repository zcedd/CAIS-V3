<?php

namespace App\Actions\User;

use App\Enums\RequestStatusCode;
use App\Models\Assistance;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Validation\ValidationException;

class AssertAssistanceWorkflowTransition
{
    /**
     * @throws ValidationException
     */
    public function __invoke(Assistance $assistance, RequestSubStatus $target, User $user): WorkflowStep
    {
        $assistance->load([
            'program',
            'currentRequestSubStatus.requestStatus',
        ]);
        $target->loadMissing('requestStatus');

        $workflow = $assistance->program?->resolvedWorkflow();

        if (! $workflow instanceof Workflow) {
            throw ValidationException::withMessages([
                'request_sub_status_id' => 'This program does not have a workflow.',
            ]);
        }

        $workflow->loadMissing(['steps.transitions', 'steps.requestStatus']);

        $targetStatus = $target->requestStatus;
        $targetStatusId = (int) $target->request_status_id;
        $targetStep = $this->stepForStatus($workflow, $targetStatusId, $targetStatus);

        if ($targetStep === null) {
            throw ValidationException::withMessages([
                'request_sub_status_id' => 'That status is not part of this program workflow.',
            ]);
        }

        if ($targetStep->permission !== null && ! $user->can($targetStep->permission)) {
            throw ValidationException::withMessages([
                'request_sub_status_id' => 'You are not allowed to move the request to that status.',
            ]);
        }

        $currentStatus = $assistance->currentRequestSubStatus?->requestStatus;
        $currentStatusId = $currentStatus !== null ? (int) $currentStatus->id : null;
        $currentStep = $currentStatusId !== null
            ? $this->stepForStatus($workflow, $currentStatusId, $currentStatus)
            : null;

        if ($currentStep !== null && ! $this->userMayAdvance($user, $assistance, $currentStep)) {
            throw ValidationException::withMessages([
                'request_sub_status_id' => 'Only the assignee for this stage can update the status.',
            ]);
        }

        if ($currentStep === null) {
            return $targetStep;
        }

        if ($currentStatusId === $targetStatusId) {
            return $targetStep;
        }

        $allowed = $currentStep->allowedTargetStatusIds();

        if ($allowed === []) {
            $allowed = $this->defaultTargets($workflow, $currentStep);
        }

        if (in_array($targetStatusId, $allowed, true)) {
            return $targetStep;
        }

        if ($targetStep->allows_skip_to_deliver && RequestStatusCode::Delivered->matches($targetStatus)) {
            return $targetStep;
        }

        if ($currentStep->allows_skip_to_deliver && RequestStatusCode::Delivered->matches($targetStatus)) {
            return $targetStep;
        }

        if (
            RequestStatusCode::Denied->matches($targetStatus)
            && ! RequestStatusCode::Closed->matches($currentStatus)
        ) {
            return $targetStep;
        }

        throw ValidationException::withMessages([
            'request_sub_status_id' => 'That status is not a valid next step for this request.',
        ]);
    }

    /**
     * @return list<int>
     */
    private function defaultTargets(Workflow $workflow, WorkflowStep $currentStep): array
    {
        $next = $workflow->steps
            ->first(static fn (WorkflowStep $step): bool => $step->sort_order > $currentStep->sort_order
                && ! ($step->requestStatus?->is_hold ?? false)
                && ! ($step->requestStatus?->is_retired ?? false));

        $targets = [];

        if ($next instanceof WorkflowStep) {
            $targets[] = (int) $next->request_status_id;
        }

        foreach ($workflow->steps as $step) {
            $code = $step->requestStatus?->code;

            if (
                $code === RequestStatusCode::OnHold
                || $code === RequestStatusCode::Denied
                || $code === RequestStatusCode::Closed
            ) {
                $targets[] = (int) $step->request_status_id;
            }
        }

        return array_values(array_unique($targets));
    }

    private function userMayAdvance(User $user, Assistance $assistance, WorkflowStep $currentStep): bool
    {
        if ($currentStep->assigned_to_id === null) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $assistance->assigned_to_id === (int) $user->id
            || (int) $currentStep->assigned_to_id === (int) $user->id;
    }

    private function stepForStatus(Workflow $workflow, int $requestStatusId, ?RequestStatus $status): ?WorkflowStep
    {
        $match = $workflow->stepForStatus($requestStatusId);

        if ($match instanceof WorkflowStep) {
            return $match;
        }

        if ($status === null) {
            return null;
        }

        return $workflow->steps->first(function (WorkflowStep $step) use ($status): bool {
            $stepStatus = $step->requestStatus;

            if ($stepStatus === null) {
                return false;
            }

            if ($status->code !== null && $stepStatus->code !== null && $status->code === $stepStatus->code) {
                return true;
            }

            return $stepStatus->name === $status->name;
        });
    }
}

<?php

namespace App\Services\Workflow;

use App\Enums\RequestStatusCode;
use App\Enums\WorkflowAssignmentType;
use App\Models\Department;
use App\Models\RequestStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class PublishWorkflowValidator
{
    /**
     * @return list<string>
     */
    public function errors(Workflow $workflow): array
    {
        $workflow->load([
            'steps.requestStatus',
            'steps.defaultSubStatus',
            'steps.assignedTo',
            'steps.assignedDepartment',
            'steps.transitions.toStep',
            'steps.transitions.toRequestStatus',
        ]);

        $errors = [];
        $steps = $workflow->steps;

        if ($steps->isEmpty()) {
            $errors[] = 'No start step configured.';
            $errors[] = 'Workflow has no steps.';
            $errors[] = 'No end step configured.';

            return $errors;
        }

        $startSteps = $steps->filter(static fn (WorkflowStep $step): bool => $step->is_start)->values();
        $endSteps = $steps->filter(static fn (WorkflowStep $step): bool => $step->is_end)->values();

        if ($startSteps->count() === 0) {
            $errors[] = 'No start step configured.';
        }

        if ($startSteps->count() > 1) {
            $errors[] = 'Exactly one start step is required.';
        }

        if ($endSteps->count() === 0) {
            $errors[] = 'No end step configured.';
        }

        $codes = [];

        foreach ($steps as $step) {
            $code = trim((string) $step->code);
            $label = $code !== '' ? $code : (string) ($step->requestStatus?->name ?? 'Unnamed step');

            if ($code === '') {
                $errors[] = $label.' has no step code.';
            } elseif (isset($codes[$code])) {
                $errors[] = 'Duplicate step code '.$code.'.';
            } else {
                $codes[$code] = true;
            }

            if ($step->request_status_id === null) {
                $errors[] = $label.' has no status mapping.';
            } elseif ($step->requestStatus === null || $step->requestStatus->is_retired) {
                $errors[] = $label.' references an inactive status.';
            }

            if ($step->default_request_sub_status_id === null) {
                $errors[] = $label.' has no required sub-status mapping.';
            } elseif ($step->defaultSubStatus === null) {
                $errors[] = $label.' references a missing sub-status.';
            } elseif ((int) $step->defaultSubStatus->request_status_id !== (int) $step->request_status_id) {
                $errors[] = $label.' sub-status does not belong to its status.';
            }

            $errors = [
                ...$errors,
                ...$this->assignmentErrors($step, $label),
                ...$this->transitionErrors($workflow, $step, $label),
            ];
        }

        $errors = [
            ...$errors,
            ...$this->reachabilityErrors($workflow),
        ];

        return array_values(array_unique($errors));
    }

    /**
     * @return list<string>
     */
    private function assignmentErrors(WorkflowStep $step, string $label): array
    {
        $type = $step->assignment_type ?? WorkflowAssignmentType::None;
        $errors = [];

        if ($step->isTaskStep() && $type === WorkflowAssignmentType::None && $step->assigned_to_id === null) {
            $errors[] = $label.' step has no assignment rule.';
        }

        if ($type === WorkflowAssignmentType::User || $step->assigned_to_id !== null) {
            $user = $step->assignedTo;

            if ($step->assigned_to_id !== null && ! $user instanceof User) {
                $errors[] = $label.' assignment references a missing user.';
            }
        }

        if ($type === WorkflowAssignmentType::Role || $type === WorkflowAssignmentType::DepartmentRole) {
            $roleName = trim((string) $step->assigned_role);

            if ($roleName === '') {
                $errors[] = $label.' assignment has no role.';
            } elseif (! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
                $errors[] = $label.' assignment references a missing role.';
            }
        }

        if ($type === WorkflowAssignmentType::DepartmentRole) {
            $department = $step->assignedDepartment;

            if ($step->assigned_department_id === null || ! $department instanceof Department) {
                $errors[] = $label.' assignment references a missing department.';
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function transitionErrors(Workflow $workflow, WorkflowStep $step, string $label): array
    {
        $statusIds = $workflow->steps
            ->pluck('request_status_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $errors = [];

        foreach ($step->transitions as $transition) {
            if (! $transition instanceof WorkflowStepTransition) {
                continue;
            }

            $targetId = (int) ($transition->to_step_id ?? 0);

            if ($targetId === 0) {
                $statusId = (int) $transition->to_request_status_id;
                $targetId = (int) ($workflow->steps
                    ->first(static fn (WorkflowStep $candidate): bool => (int) $candidate->request_status_id === $statusId)
                    ?->id ?? 0);
            }

            if ($targetId === 0) {
                $errors[] = $label.' transition has no destination step.';

                continue;
            }

            if (! $workflow->steps->contains(static fn (WorkflowStep $candidate): bool => (int) $candidate->id === $targetId)) {
                $errors[] = $label.' transition has no destination step.';
            }
        }

        if (! $step->is_end && $step->transitions->isEmpty() && ! $this->isParking($step)) {
            $errors[] = $label.' has no outgoing transitions.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function reachabilityErrors(Workflow $workflow): array
    {
        $steps = $workflow->steps;
        $start = $steps->first(static fn (WorkflowStep $step): bool => $step->is_start);

        if (! $start instanceof WorkflowStep) {
            return [];
        }

        $byStepId = $steps->keyBy(static fn (WorkflowStep $step): int => (int) $step->id);
        $reachable = [];
        $queue = [(int) $start->id];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($reachable[$currentId])) {
                continue;
            }

            $reachable[$currentId] = true;
            $current = $byStepId->get($currentId);

            if (! $current instanceof WorkflowStep) {
                continue;
            }

            foreach ($current->allowedTargetStepIds() as $targetId) {
                $queue[] = $targetId;
            }

            foreach ($current->allowedTargetStatusIds() as $statusId) {
                $target = $steps->first(
                    static fn (WorkflowStep $step): bool => (int) $step->request_status_id === $statusId,
                );

                if ($target instanceof WorkflowStep) {
                    $queue[] = (int) $target->id;
                }
            }
        }

        $errors = [];

        foreach ($steps as $step) {
            $label = trim((string) $step->code) !== ''
                ? (string) $step->code
                : (string) ($step->requestStatus?->name ?? 'Unnamed step');

            if (! isset($reachable[(int) $step->id]) && ! $this->isParking($step)) {
                $errors[] = $label.' is not reachable from the start step.';
            }

            if ($step->is_end || $this->isParking($step)) {
                continue;
            }

            if (! $this->canReachEnd($step, $byStepId, $workflow)) {
                $errors[] = $label.' cannot reach an end step.';
            }
        }

        if ($this->hasInvalidHappyPathCycle($workflow, $byStepId)) {
            $errors[] = 'Workflow contains an invalid circular path.';
        }

        return $errors;
    }

    /**
     * @param  Collection<int, WorkflowStep>  $byStepId
     */
    private function canReachEnd(WorkflowStep $from, $byStepId, Workflow $workflow): bool
    {
        $seen = [];
        $queue = [(int) $from->id];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($seen[$currentId])) {
                continue;
            }

            $seen[$currentId] = true;
            $current = $byStepId->get($currentId);

            if (! $current instanceof WorkflowStep) {
                continue;
            }

            if ($current->is_end) {
                return true;
            }

            foreach ($this->outgoingStepIds($current, $workflow) as $targetId) {
                $queue[] = $targetId;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, WorkflowStep>  $byStepId
     */
    private function hasInvalidHappyPathCycle(Workflow $workflow, $byStepId): bool
    {
        $visiting = [];
        $visited = [];

        $visit = function (int $stepId) use (&$visit, &$visiting, &$visited, $byStepId, $workflow): bool {
            if (isset($visiting[$stepId])) {
                return true;
            }

            if (isset($visited[$stepId])) {
                return false;
            }

            $step = $byStepId->get($stepId);

            if (! $step instanceof WorkflowStep || $this->isParking($step) || $step->is_end) {
                $visited[$stepId] = true;

                return false;
            }

            $visiting[$stepId] = true;

            foreach ($this->outgoingStepIds($step, $workflow) as $targetId) {
                $target = $byStepId->get($targetId);

                if ($target instanceof WorkflowStep && ($this->isParking($target) || $target->is_end)) {
                    continue;
                }

                if ($visit($targetId)) {
                    return true;
                }
            }

            unset($visiting[$stepId]);
            $visited[$stepId] = true;

            return false;
        };

        foreach ($workflow->steps as $step) {
            if ($this->isParking($step) || $step->is_end) {
                continue;
            }

            if ($visit((int) $step->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function outgoingStepIds(WorkflowStep $step, Workflow $workflow): array
    {
        $ids = $step->allowedTargetStepIds();

        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        foreach ($step->allowedTargetStatusIds() as $statusId) {
            $target = $workflow->steps->first(
                static fn (WorkflowStep $candidate): bool => (int) $candidate->request_status_id === $statusId,
            );

            if ($target instanceof WorkflowStep) {
                $ids[] = (int) $target->id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function isParking(WorkflowStep $step): bool
    {
        $status = $step->requestStatus;

        if ($status instanceof RequestStatus && ($status->is_hold || RequestStatusCode::OnHold->matches($status))) {
            return true;
        }

        return RequestStatusCode::Denied->matches($status);
    }
}

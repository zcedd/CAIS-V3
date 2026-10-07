<?php

namespace App\Actions\Admin;

use App\Actions\User\RecordAssistanceAssignment;
use App\Models\Assistance;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Validation\ValidationException;

class ReassignWorkflowTask
{
    public function __construct(
        private RecordAssistanceAssignment $recordAssistanceAssignment,
        private WorkflowEngine $workflowEngine,
    ) {}

    public function __invoke(
        Workflow $workflow,
        Assistance $assistance,
        ?User $actor,
        ?User $assignee,
        ?string $remark = null,
        bool $override = false,
    ): Assistance {
        $belongsToWorkflow = (int) $assistance->workflow_id === (int) $workflow->id
            || (int) $assistance->program?->workflow_id === (int) $workflow->id
            || (int) $assistance->resolvedWorkflow()->id === (int) $workflow->id;

        if (! $belongsToWorkflow) {
            throw ValidationException::withMessages([
                'assistance' => 'That task does not belong to this workflow.',
            ]);
        }

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'assigned_to_id' => 'An authorized user is required to reassign this task.',
            ]);
        }

        $remark ??= $override ? 'Super Admin override' : 'Workflow reassign';
        $task = $this->workflowEngine->currentTask($assistance);

        if ($task !== null) {
            $this->workflowEngine->reassignTask($task, $actor, $assignee, $remark);

            return $assistance->refresh();
        }

        return ($this->recordAssistanceAssignment)($assistance, $actor, $assignee, $remark);
    }
}

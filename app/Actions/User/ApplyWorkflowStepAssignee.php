<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\User;
use App\Models\WorkflowStep;

class ApplyWorkflowStepAssignee
{
    public function __construct(
        private RecordAssistanceAssignment $recordAssistanceAssignment,
    ) {}

    public function __invoke(
        Assistance $assistance,
        WorkflowStep $step,
        User $actor,
        bool $notify = true,
    ): Assistance {
        if ($step->assigned_to_id === null) {
            return $assistance;
        }

        $assignee = User::query()->find($step->assigned_to_id);

        if (! $assignee instanceof User) {
            return $assistance;
        }

        return ($this->recordAssistanceAssignment)(
            $assistance,
            $actor,
            $assignee,
            'Workflow auto-assign',
            $notify,
        );
    }
}

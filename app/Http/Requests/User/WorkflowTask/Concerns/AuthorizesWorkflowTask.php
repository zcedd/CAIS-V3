<?php

namespace App\Http\Requests\User\WorkflowTask\Concerns;

use App\Models\Assistance;
use App\Models\Department;
use App\Models\WorkflowTask;

trait AuthorizesWorkflowTask
{
    public function assistance(): Assistance
    {
        /** @var WorkflowTask $task */
        $task = $this->route('task');
        $task->loadMissing('workflowInstance.assistance.program');

        $assistance = $task->workflowInstance?->assistance;

        if (! $assistance instanceof Assistance) {
            abort(404);
        }

        $department = $this->route('department');

        if ($department instanceof Department
            && (int) $assistance->program?->department_id !== (int) $department->id) {
            abort(404);
        }

        return $assistance;
    }
}

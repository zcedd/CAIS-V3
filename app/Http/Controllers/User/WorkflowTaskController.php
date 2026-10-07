<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\WorkflowTask\AssignRequest;
use App\Http\Requests\User\WorkflowTask\ClaimRequest;
use App\Http\Requests\User\WorkflowTask\CompleteRequest;
use App\Models\Department;
use App\Models\WorkflowTask;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Http\RedirectResponse;

class WorkflowTaskController extends Controller
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
    ) {}

    public function claim(
        ClaimRequest $request,
        Department $department,
        WorkflowTask $task,
    ): RedirectResponse {
        $this->workflowEngine->claimTask($task, $request->user());

        return back()->with('success', 'Task claimed.');
    }

    public function complete(
        CompleteRequest $request,
        Department $department,
        WorkflowTask $task,
    ): RedirectResponse {
        $transition = $request->transition();

        match ($request->validated('action')) {
            'return' => $this->workflowEngine->returnTask(
                $task,
                $request->user(),
                $transition,
                $request->payload(),
            ),
            'reject' => $this->workflowEngine->rejectTask(
                $task,
                $request->user(),
                $transition,
                $request->payload(),
            ),
            default => $this->workflowEngine->executeTransition(
                $request->assistance(),
                $request->user(),
                $transition,
                $request->payload(),
                $task,
            ),
        };

        return back()->with('success', 'Task updated.');
    }

    public function reassign(
        AssignRequest $request,
        Department $department,
        WorkflowTask $task,
    ): RedirectResponse {
        $this->workflowEngine->reassignTask(
            $task,
            $request->user(),
            $request->assignee(),
            $request->validated('remark'),
        );

        return back()->with('success', 'Task reassigned.');
    }
}

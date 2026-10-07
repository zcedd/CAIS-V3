<?php

namespace App\Actions\Admin;

use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use App\Services\User\WorkflowService;
use Illuminate\Support\Facades\DB;

class DuplicateWorkflow
{
    public function __construct(
        private WorkflowService $workflowService,
    ) {}

    public function __invoke(Workflow $workflow, string $name, string $code): Workflow
    {
        return DB::transaction(function () use ($workflow, $name, $code): Workflow {
            $workflow->load(['steps.transitions', 'department']);

            $department = $workflow->department;

            if (! $department instanceof Department) {
                $department = Department::query()->findOrFail($workflow->department_id);
            }

            $copy = $workflow->replicate([
                'is_default',
            ]);
            $copy->fill([
                'name' => $name,
                'code' => $this->workflowService->uniqueCode($department, $code),
                'version' => 1,
                'status' => WorkflowStatus::Draft,
                'source_workflow_id' => $workflow->id,
                'is_default' => false,
            ])->save();

            $stepMap = [];

            foreach ($workflow->steps as $step) {
                $newStep = $step->replicate();
                $newStep->workflow_id = $copy->id;
                $newStep->save();
                $stepMap[(int) $step->id] = $newStep;
            }

            foreach ($workflow->steps as $step) {
                $from = $stepMap[(int) $step->id] ?? null;

                if (! $from instanceof WorkflowStep) {
                    continue;
                }

                foreach ($step->transitions as $transition) {
                    WorkflowStepTransition::query()->create([
                        'workflow_step_id' => $from->id,
                        'to_step_id' => $transition->to_step_id !== null
                            ? ($stepMap[(int) $transition->to_step_id]?->id ?? null)
                            : null,
                        'to_request_status_id' => $transition->to_request_status_id,
                        'action' => $transition->action,
                        'label' => $transition->label,
                        'requires_comment' => $transition->requires_comment,
                        'conditions' => $transition->conditions,
                    ]);
                }
            }

            return $copy->fresh(['steps.transitions']) ?? $copy;
        });
    }
}

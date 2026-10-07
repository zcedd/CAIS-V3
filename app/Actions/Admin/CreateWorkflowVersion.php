<?php

namespace App\Actions\Admin;

use App\Enums\WorkflowStatus;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use Illuminate\Support\Facades\DB;

class CreateWorkflowVersion
{
    public function __invoke(Workflow $workflow, ?string $name = null): Workflow
    {
        return DB::transaction(function () use ($workflow, $name): Workflow {
            $workflow->load(['steps.transitions']);

            $nextVersion = (int) Workflow::query()
                ->where('department_id', $workflow->department_id)
                ->where('code', $workflow->code)
                ->max('version') + 1;

            $copy = $workflow->replicate([
                'is_default',
            ]);
            $copy->fill([
                'name' => $name ?? $workflow->name,
                'version' => $nextVersion,
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
                    $toStepId = $transition->to_step_id !== null
                        ? ($stepMap[(int) $transition->to_step_id]?->id ?? null)
                        : null;

                    WorkflowStepTransition::query()->create([
                        'workflow_step_id' => $from->id,
                        'to_step_id' => $toStepId,
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

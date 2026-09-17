<?php

namespace Database\Factories;

use App\Enums\WorkflowTaskPriority;
use App\Enums\WorkflowTaskStatus;
use App\Models\AssistanceWorkflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTask>
 */
class WorkflowTaskFactory extends Factory
{
    protected $model = WorkflowTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistance_workflow_id' => AssistanceWorkflow::factory(),
            'workflow_step_id' => WorkflowStep::factory(),
            'assigned_to_id' => null,
            'assigned_by_id' => null,
            'status' => WorkflowTaskStatus::Pending,
            'priority' => WorkflowTaskPriority::Normal,
            'assigned_at' => null,
            'started_at' => null,
            'due_at' => null,
            'completed_at' => null,
            'remarks' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\WorkflowInstanceStatus;
use App\Models\Assistance;
use App\Models\AssistanceWorkflow;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistanceWorkflow>
 */
class AssistanceWorkflowFactory extends Factory
{
    protected $model = AssistanceWorkflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistance_id' => Assistance::factory(),
            'workflow_id' => Workflow::factory(),
            'workflow_version' => 1,
            'current_step_id' => null,
            'status' => WorkflowInstanceStatus::Active,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}

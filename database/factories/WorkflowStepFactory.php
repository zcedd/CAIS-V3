<?php

namespace Database\Factories;

use App\Enums\RequestStatusCode;
use App\Enums\RequestSubStatusCode;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\RequestStatusCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $catalog = app(RequestStatusCatalog::class);
        $catalog->ensure();

        return [
            'workflow_id' => Workflow::factory(),
            'request_status_id' => $catalog->parentId(RequestStatusCode::Submitted),
            'sort_order' => 10,
            'default_request_sub_status_id' => $catalog->reasonId(RequestSubStatusCode::AwaitingReview),
            'sla_hours' => 48,
            'requires_assignee' => false,
            'assigned_to_id' => null,
            'permission' => null,
            'allows_skip_to_deliver' => false,
        ];
    }
}

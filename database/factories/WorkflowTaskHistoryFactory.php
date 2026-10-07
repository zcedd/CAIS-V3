<?php

namespace Database\Factories;

use App\Enums\WorkflowTaskHistoryAction;
use App\Enums\WorkflowTaskStatus;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTaskHistory>
 */
class WorkflowTaskHistoryFactory extends Factory
{
    protected $model = WorkflowTaskHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_task_id' => WorkflowTask::factory(),
            'action' => WorkflowTaskHistoryAction::Created,
            'from_status' => null,
            'to_status' => WorkflowTaskStatus::Pending,
            'performed_by' => null,
            'remarks' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}

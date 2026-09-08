<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Workflow;
use App\Services\Workflow\RequestStatusCatalog;
use App\Support\RequestStatusCode;
use App\Support\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $catalog = app(RequestStatusCatalog::class);
        $catalog->ensure();

        $submittedId = $catalog->parentId(RequestStatusCode::Submitted);

        return [
            'department_id' => Department::query()->value('id')
                ?? Department::query()->create(['name' => fake()->company()])->id,
            'name' => 'Standard',
            'template' => WorkflowTemplate::Standard,
            'is_default' => false,
            'staff_entry_request_status_id' => $submittedId,
            'public_entry_request_status_id' => $submittedId,
        ];
    }
}

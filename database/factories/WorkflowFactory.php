<?php

namespace Database\Factories;

use App\Enums\RequestStatusCode;
use App\Enums\WorkflowTemplate;
use App\Models\Department;
use App\Models\Workflow;
use App\Services\Workflow\RequestStatusCatalog;
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
            'template' => WorkflowTemplate::Standard->value,
            'is_default' => false,
            'staff_entry_request_status_id' => $submittedId,
            'public_entry_request_status_id' => $submittedId,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\RequestStatusCode;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowTemplate;
use App\Models\Department;
use App\Models\Workflow;
use App\Services\Workflow\RequestStatusCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $name = fake()->unique()->words(2, true);

        return [
            'department_id' => Department::query()->value('id')
                ?? Department::query()->create(['name' => fake()->company()])->id,
            'name' => Str::title($name),
            'code' => Str::upper(Str::slug($name, '_')),
            'version' => 1,
            'status' => WorkflowStatus::Active,
            'template' => WorkflowTemplate::Standard->value,
            'is_default' => false,
            'staff_entry_request_status_id' => $submittedId,
            'public_entry_request_status_id' => $submittedId,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => WorkflowStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => WorkflowStatus::Published]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => WorkflowStatus::Inactive]);
    }
}

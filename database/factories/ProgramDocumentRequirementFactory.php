<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Program;
use App\Models\ProgramDocumentRequirement;
use App\Support\DocumentRequirementMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramDocumentRequirement>
 */
class ProgramDocumentRequirementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::query()->value('id') ?? Program::create([
                'name' => fake()->words(2, true),
                'descriptions' => fake()->sentence(),
                'start_at' => now()->toDateString(),
                'end_at' => null,
                'department_id' => Department::query()->value('id')
                    ?? Department::create(['name' => fake()->company()])->id,
                'is_closed' => false,
                'is_organization' => false,
            ])->id,
            'document_type_id' => DocumentType::query()->value('id')
                ?? DocumentType::factory()->create()->id,
            'is_required' => true,
            'required_before' => DocumentRequirementMilestone::Verified,
            'sort_order' => 0,
        ];
    }

    public function forProgram(Program $program): static
    {
        return $this->state(fn (array $attributes): array => [
            'program_id' => $program->id,
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => false,
        ]);
    }

    public function beforeDelivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'required_before' => DocumentRequirementMilestone::Delivered,
        ]);
    }
}

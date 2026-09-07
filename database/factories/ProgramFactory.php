<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Program;
use App\Support\ProgramKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'descriptions' => fake()->sentence(),
            'start_at' => now()->toDateString(),
            'end_at' => null,
            'department_id' => Department::query()->value('id')
                ?? Department::create(['name' => fake()->company()])->id,
            'is_closed' => false,
            'is_organization' => false,
            'kind' => ProgramKind::Standalone,
            'parent_id' => null,
            'batch_number' => null,
            'batch_name' => null,
        ];
    }

    public function standalone(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ProgramKind::Standalone,
            'parent_id' => null,
            'batch_number' => null,
            'batch_name' => null,
        ]);
    }

    public function scheme(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ProgramKind::Scheme,
            'parent_id' => null,
            'batch_number' => null,
            'batch_name' => null,
        ]);
    }

    public function batch(?Program $scheme = null): static
    {
        return $this->state(function (array $attributes) use ($scheme): array {
            $parent = $scheme ?? Program::factory()->scheme()->create([
                'department_id' => $attributes['department_id'] ?? Department::query()->value('id')
                    ?? Department::create(['name' => fake()->company()])->id,
            ]);

            $nextNumber = ((int) $parent->batches()->max('batch_number')) + 1;
            $batchName = 'Batch '.$nextNumber;

            return [
                'kind' => ProgramKind::Batch,
                'parent_id' => $parent->id,
                'department_id' => $parent->department_id,
                'is_organization' => $parent->is_organization,
                'batch_number' => $nextNumber,
                'batch_name' => $batchName,
                'name' => Program::composeBatchDisplayName($parent->name, $batchName),
                'descriptions' => $parent->descriptions,
            ];
        });
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_closed' => true,
        ]);
    }

    public function organization(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_organization' => true,
        ]);
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes): array => [
            'department_id' => $department->id,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\ProgramFieldType;
use App\Models\Department;
use App\Models\Program;
use App\Models\ProgramField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgramField>
 */
class ProgramFieldFactory extends Factory
{
    protected $model = ProgramField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

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
            'label' => Str::title($label),
            'key' => Str::slug($label, '_'),
            'type' => ProgramFieldType::Text->value,
            'options' => null,
            'is_required' => false,
            'show_in_table' => false,
            'sort_order' => 0,
        ];
    }

    public function forProgram(Program $program): static
    {
        return $this->state(fn (array $attributes): array => [
            'program_id' => $program->id,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => true,
        ]);
    }

    public function showInTable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'show_in_table' => true,
        ]);
    }

    public function select(array $options = ['Option A', 'Option B']): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ProgramFieldType::Select->value,
            'options' => $options,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'department_id' => Department::query()->value('id') ?? Department::create(['name' => fake()->company()])->id,
            'item_unit_measurement_id' => ItemUnitMeasurement::query()->value('id')
                ?? ItemUnitMeasurement::factory()->create()->id,
        ];
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes): array => [
            'department_id' => $department->id,
        ]);
    }

    public function withUnitMeasurement(ItemUnitMeasurement $unitMeasurement): static
    {
        return $this->state(fn (array $attributes): array => [
            'item_unit_measurement_id' => $unitMeasurement->id,
        ]);
    }
}

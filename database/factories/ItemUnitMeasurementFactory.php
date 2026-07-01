<?php

namespace Database\Factories;

use App\Models\ItemUnitMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemUnitMeasurement>
 */
class ItemUnitMeasurementFactory extends Factory
{
    protected $model = ItemUnitMeasurement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}

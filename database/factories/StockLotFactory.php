<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Item;
use App\Models\StockLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLot>
 */
class StockLotFactory extends Factory
{
    protected $model = StockLot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $item = Item::factory()->create();

        return [
            'department_id' => $item->department_id,
            'item_id' => $item->id,
            'batch_number' => fake()->optional()->bothify('LOT-####'),
            'expires_at' => fake()->optional()->dateTimeBetween('+1 month', '+1 year'),
            'received_at' => now(),
        ];
    }

    public function forItem(Item $item): static
    {
        return $this->state(fn (array $attributes): array => [
            'department_id' => $item->department_id,
            'item_id' => $item->id,
        ]);
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes): array => [
            'department_id' => $department->id,
        ]);
    }
}

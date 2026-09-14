<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockMovementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $item = Item::factory()->create();

        return [
            'department_id' => $item->department_id,
            'item_id' => $item->id,
            'program_id' => null,
            'stock_lot_id' => null,
            'type' => StockMovementType::Receipt,
            'quantity' => fake()->numberBetween(1, 50),
            'assistance_item_id' => null,
            'reverses_movement_id' => null,
            'user_id' => User::factory(),
            'reason' => null,
            'occurred_at' => now(),
        ];
    }
}

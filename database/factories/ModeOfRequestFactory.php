<?php

namespace Database\Factories;

use App\Models\ModeOfRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModeOfRequest>
 */
class ModeOfRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}

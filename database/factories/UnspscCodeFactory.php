<?php

namespace Database\Factories;

use App\Models\UnspscCode;
use App\Support\UnspscCodeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnspscCode>
 */
class UnspscCodeFactory extends Factory
{
    protected $model = UnspscCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = str_pad((string) fake()->unique()->numberBetween(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        return [
            'code' => $code,
            'title' => fake()->words(3, true),
            'level' => UnspscCodeLevel::Commodity,
            'parent_id' => null,
            'segment_code' => substr($code, 0, 2).'000000',
            'family_code' => substr($code, 0, 4).'0000',
            'class_code' => substr($code, 0, 6).'00',
            'is_curated' => true,
            'version' => 'curated-2026',
        ];
    }

    public function curated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_curated' => true,
        ]);
    }

    public function notCurated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_curated' => false,
        ]);
    }
}

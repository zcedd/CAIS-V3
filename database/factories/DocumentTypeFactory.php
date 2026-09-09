<?php

namespace Database\Factories;

use App\Enums\DocumentTypeSlug;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
        ];
    }

    public function validId(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Valid ID',
            'slug' => DocumentTypeSlug::ValidId->value,
        ]);
    }
}

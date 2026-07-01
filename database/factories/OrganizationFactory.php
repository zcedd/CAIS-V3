<?php

namespace Database\Factories;

use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cais_number' => 'ORG-TEST-'.fake()->unique()->numerify('####'),
            'name' => fake()->company(),
            'beneficiary_id' => Individual::factory(),
            'mobile_number' => fake()->optional()->phoneNumber(),
            'total_member' => fake()->numberBetween(1, 50),
        ];
    }
}

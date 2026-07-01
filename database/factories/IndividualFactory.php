<?php

namespace Database\Factories;

use App\Models\Individual;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Individual>
 */
class IndividualFactory extends Factory
{
    protected $model = Individual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cais_number' => 'IND-TEST-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'birthday' => fake()->optional()->date(),
            'sex' => fake()->randomElement(['Male', 'Female']),
            'other_address' => fake()->optional()->address(),
            'mobile_number' => fake()->optional()->phoneNumber(),
            'address_barangay_id' => $this->createBarangayId(),
            'indigenous' => false,
            'pwd' => false,
            'is_4ps_beneficiary' => false,
            'is_solo_parent' => false,
        ];
    }

    private function createBarangayId(): int
    {
        $cityId = DB::table('address_cities')->insertGetId([
            'name' => 'Factory City',
            'zipcode' => '1000',
            'excel_name' => 'Factory City',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('address_barangays')->insertGetId([
            'name' => 'Factory Barangay',
            'address_city_id' => $cityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

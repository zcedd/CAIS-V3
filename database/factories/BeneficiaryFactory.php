<?php

namespace Database\Factories;

use App\Models\Beneficiary;
use App\Models\Individual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    protected $model = Beneficiary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $individual = Individual::factory()->create();

        return [
            'cais_number' => $individual->cais_number,
            'name' => $individual->fullName(),
            'beneficiable_type' => Individual::class,
            'beneficiable_id' => $individual->id,
        ];
    }
}

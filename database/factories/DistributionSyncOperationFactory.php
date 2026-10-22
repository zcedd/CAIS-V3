<?php

namespace Database\Factories;

use App\Enums\DistributionSyncOperationStatus;
use App\Enums\DistributionSyncOperationType;
use App\Models\DistributionSyncOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributionSyncOperation>
 */
class DistributionSyncOperationFactory extends Factory
{
    /**
     * Batch and item ids are supplied by the caller. Those factories do not build a valid distribution.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_operation_id' => fake()->uuid(),
            'user_id' => User::factory(),
            'type' => DistributionSyncOperationType::Release,
            'status' => DistributionSyncOperationStatus::Applied,
            'device_captured_at' => now(),
            'result' => ['status' => 'applied'],
            'error' => null,
        ];
    }
}

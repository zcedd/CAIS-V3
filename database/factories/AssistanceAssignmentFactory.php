<?php

namespace Database\Factories;

use App\Models\AssistanceAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistanceAssignment>
 */
class AssistanceAssignmentFactory extends Factory
{
    protected $model = AssistanceAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignee = User::factory();

        return [
            'assigned_from_id' => null,
            'assigned_to_id' => $assignee,
            'assigned_by_id' => $assignee,
            'remark' => null,
        ];
    }
}

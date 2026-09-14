<?php

use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users cannot mutate assistance through a different program url', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $programA = Program::create([
        'name' => 'Program A',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $programB = Program::create([
        'name' => 'Program B',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $assistance = Assistance::create([
        'program_id' => $programB->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('user.programs.assistances.destroy', [
            'department' => $department->slug,
            'program' => $programA->id,
            'assistance' => $assistance->id,
        ]))
        ->assertNotFound();

    $this->actingAs($user)
        ->put(route('user.programs.assistances.update', [
            'department' => $department->slug,
            'program' => $programA->id,
            'assistance' => $assistance->id,
        ]), [
            'beneficiary_id' => null,
            'date_requested' => now()->toDateString(),
            'item_ids' => [],
        ])
        ->assertNotFound();

    expect(Assistance::query()->find($assistance->id))->not->toBeNull();
});

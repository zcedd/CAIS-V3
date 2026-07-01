<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can delete unlinked funds in their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $fund = Fund::factory()->create([
        'name' => 'General Fund',
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->delete(route('user.funds.destroy', [
        'department' => $department->slug,
        'fund' => $fund->id,
    ]));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertSoftDeleted('funds', [
        'id' => $fund->id,
    ]);
});

test('authenticated users cannot delete funds linked to programs', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $fund = Fund::factory()->create([
        'name' => 'Linked Fund',
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Assistance Program',
        'descriptions' => 'Program description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $program->fund()->attach($fund->id);

    $response = $this->actingAs($user)->from(route('user.funds.index', ['department' => $department->slug]))
        ->delete(route('user.funds.destroy', [
            'department' => $department->slug,
            'fund' => $fund->id,
        ]));

    $response->assertRedirect(route('user.funds.index', ['department' => $department->slug]));
    $response->assertSessionHasErrors('fund');

    $this->assertDatabaseHas('funds', [
        'id' => $fund->id,
        'deleted_at' => null,
    ]);
});

test('authenticated users cannot delete funds from another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $fundInB = Fund::factory()->create([
        'name' => 'Department B Fund',
        'department_id' => $departmentB->id,
    ]);

    $this->actingAs($user)
        ->delete(route('user.funds.destroy', [
            'department' => $departmentB->slug,
            'fund' => $fundInB->id,
        ]))
        ->assertForbidden();

    $this->assertDatabaseHas('funds', [
        'id' => $fundInB->id,
        'deleted_at' => null,
    ]);
});

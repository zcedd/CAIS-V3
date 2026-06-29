<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view an assistance profile', function () {
    $department = Department::create(['name' => 'Department A']);
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => now()->toDateString(),
        'user_id' => User::factory()->create()->id,
    ]);

    $response = $this->get(route('user.assistances.show', [
        'department' => $department->slug,
        'program' => $program->id,
        'assistance' => $assistance->id,
    ]));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view an assistance profile in their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => '2024-03-01',
        'user_id' => $user->id,
        'remark' => 'Needs follow-up',
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'specification' => '25 kg',
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->get(route('user.assistances.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/assistances/show')
            ->where('program.id', $program->id)
            ->where('department.slug', $department->slug)
            ->where('assistance.id', $assistance->id)
            ->where('assistance.status', 'Pending')
            ->where('assistance.cais_number', '—')
            ->where('assistance.beneficiary_name', '—')
            ->where('assistance.beneficiary_type', null)
            ->where('assistance.remark', 'Needs follow-up')
            ->where('assistance.items.0.name', 'Rice')
            ->where('assistance.items.0.quantity', 2)
            ->where('assistance.items.0.unit', 'kg')
            ->where('assistance.items.0.is_received', false));
});

test('authenticated users cannot view assistance from another department program', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $programInB = Program::create([
        'name' => 'Other Program',
        'descriptions' => '',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $assistance = Assistance::create([
        'program_id' => $programInB->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.assistances.show', [
            'department' => $departmentA->slug,
            'program' => $programInB->id,
            'assistance' => $assistance->id,
        ]))
        ->assertNotFound();
});

test('authenticated users cannot view assistance that does not belong to the program', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $programA = Program::create([
        'name' => 'Program A',
        'descriptions' => '',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $programB = Program::create([
        'name' => 'Program B',
        'descriptions' => '',
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
        ->get(route('user.assistances.show', [
            'department' => $department->slug,
            'program' => $programA->id,
            'assistance' => $assistance->id,
        ]))
        ->assertNotFound();
});

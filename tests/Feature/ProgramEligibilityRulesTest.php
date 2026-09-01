<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\ProgramEligibilityRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creating a program can store eligibility rules and item caps', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), [
            'name' => 'PWD Relief',
            'descriptions' => 'Limited relief',
            'start_at' => '2026-01-01',
            'fund_ids' => [$fund->id],
            'item_ids' => [$item->id],
            'cooldown_days' => 60,
            'require_pwd' => true,
            'item_caps' => [
                ['item_id' => $item->id, 'max_released_per_year' => 3],
            ],
        ])
        ->assertRedirect();

    $program = Program::query()->where('name', 'PWD Relief')->first();

    expect($program)->not->toBeNull();

    $this->assertDatabaseHas('program_eligibility_rules', [
        'program_id' => $program->id,
        'cooldown_days' => 60,
        'require_pwd' => true,
    ]);

    $this->assertDatabaseHas('program_item_caps', [
        'program_id' => $program->id,
        'item_id' => $item->id,
        'max_released_per_year' => 3,
    ]);
});

test('organization programs do not persist demographic filters', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $department->slug]), [
            'name' => 'Org Aid',
            'descriptions' => 'For organizations',
            'start_at' => '2026-01-01',
            'is_organization' => true,
            'fund_ids' => [$fund->id],
            'item_ids' => [$item->id],
            'require_pwd' => true,
            'require_4ps' => true,
        ])
        ->assertRedirect();

    $program = Program::query()->where('name', 'Org Aid')->first();
    $rule = ProgramEligibilityRule::query()->where('program_id', $program->id)->first();

    expect($rule->require_pwd)->toBeFalse()
        ->and($rule->require_4ps)->toBeFalse();
});

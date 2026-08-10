<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\ProgramField;
use App\Models\User;
use App\Support\ProgramFieldType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{fund: Fund, item: Item}
 */
function createProgramFieldFixtures(Department $department): array
{
    $fund = Fund::create([
        'name' => 'General Fund',
        'year' => '2026',
        'amount' => 1000,
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'pc']);

    $item = Item::create([
        'name' => 'Kit',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    return ['fund' => $fund, 'item' => $item];
}

test('program create can define custom fields', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['fund' => $fund, 'item' => $item] = createProgramFieldFixtures($department);

    $response = $this->actingAs($user)->post(route('user.programs.store', [
        'department' => $department->slug,
    ]), [
        'name' => 'Livelihood Program',
        'descriptions' => 'Support details',
        'start_at' => '2026-01-01',
        'end_at' => null,
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
        'fields' => [
            [
                'label' => 'Household Size',
                'type' => ProgramFieldType::Number,
                'is_required' => true,
                'show_in_table' => true,
                'sort_order' => 0,
            ],
            [
                'label' => 'Sector',
                'type' => ProgramFieldType::Select,
                'options' => ['Farming', 'Fishing'],
                'is_required' => false,
                'show_in_table' => false,
                'sort_order' => 1,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $program = Program::query()->where('name', 'Livelihood Program')->first();
    expect($program)->not->toBeNull();

    $this->assertDatabaseHas('program_fields', [
        'program_id' => $program->id,
        'label' => 'Household Size',
        'key' => 'household_size',
        'type' => ProgramFieldType::Number,
        'is_required' => 1,
        'show_in_table' => 1,
    ]);

    $this->assertDatabaseHas('program_fields', [
        'program_id' => $program->id,
        'label' => 'Sector',
        'key' => 'sector',
        'type' => ProgramFieldType::Select,
    ]);

    $sector = ProgramField::query()
        ->where('program_id', $program->id)
        ->where('key', 'sector')
        ->first();

    expect($sector?->options)->toBe(['Farming', 'Fishing']);
});

test('program update syncs custom fields', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['fund' => $fund, 'item' => $item] = createProgramFieldFixtures($department);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $program->fund()->attach($fund->id);
    $program->item()->attach($item->id);

    $keep = ProgramField::factory()->forProgram($program)->create([
        'label' => 'Keep Me',
        'key' => 'keep_me',
        'type' => ProgramFieldType::Text,
        'show_in_table' => false,
    ]);

    $remove = ProgramField::factory()->forProgram($program)->create([
        'label' => 'Remove Me',
        'key' => 'remove_me',
        'type' => ProgramFieldType::Text,
    ]);

    $response = $this->actingAs($user)->put(route('user.programs.update', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => $program->getRawOriginal('start_at'),
        'end_at' => null,
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
        'fields' => [
            [
                'id' => $keep->id,
                'label' => 'Keep Me Updated',
                'type' => ProgramFieldType::Text,
                'is_required' => true,
                'show_in_table' => true,
                'sort_order' => 0,
            ],
            [
                'label' => 'New Field',
                'type' => ProgramFieldType::Boolean,
                'is_required' => false,
                'show_in_table' => false,
                'sort_order' => 1,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $keep->refresh();
    expect($keep->label)->toBe('Keep Me Updated')
        ->and($keep->is_required)->toBeTrue()
        ->and($keep->show_in_table)->toBeTrue();

    expect(ProgramField::query()->whereKey($remove->id)->exists())->toBeFalse();
    expect(ProgramField::withTrashed()->whereKey($remove->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('program_fields', [
        'program_id' => $program->id,
        'label' => 'New Field',
        'type' => ProgramFieldType::Boolean,
    ]);
});

test('select fields require options', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['fund' => $fund, 'item' => $item] = createProgramFieldFixtures($department);

    $response = $this->actingAs($user)->post(route('user.programs.store', [
        'department' => $department->slug,
    ]), [
        'name' => 'Broken Program',
        'descriptions' => 'Details',
        'start_at' => '2026-01-01',
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
        'fields' => [
            [
                'label' => 'Sector',
                'type' => ProgramFieldType::Select,
                'options' => [],
                'is_required' => false,
                'show_in_table' => false,
                'sort_order' => 0,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('fields.0.options');
});

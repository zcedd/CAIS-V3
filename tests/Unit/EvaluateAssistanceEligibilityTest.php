<?php

use App\Actions\User\EvaluateAssistanceEligibility;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\ProgramEligibilityRule;
use App\Models\ProgramItemCap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{program: Program, beneficiary: Beneficiary, individual: Individual, item: Item, user: User}
 */
function createEligibilityFixtures(array $individualAttributes = []): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Relief',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $individual = Individual::factory()->create($individualAttributes);

    $beneficiary = $individual->beneficiaryRecord;

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program->item()->attach($item->id);

    return compact('program', 'beneficiary', 'individual', 'item', 'user');
}

test('it hard-blocks when the program requires pwd and the beneficiary is not', function () {
    ['program' => $program, 'beneficiary' => $beneficiary] = createEligibilityFixtures([
        'pwd' => false,
    ]);

    ProgramEligibilityRule::query()->create([
        'program_id' => $program->id,
        'require_pwd' => true,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)($program->fresh(), $beneficiary);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['severity'])->toBe('hard')
        ->and($findings[0]['code'])->toBe('demographic_pwd');
});

test('it warns when the beneficiary already has an open request', function () {
    ['program' => $program, 'beneficiary' => $beneficiary, 'user' => $user] = createEligibilityFixtures();

    Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => $user->id,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)($program, $beneficiary);

    expect(collect($findings)->pluck('code')->all())->toContain('open_request')
        ->and(collect($findings)->firstWhere('code', 'open_request')['severity'])->toBe('soft');
});

test('it warns when the beneficiary is still in cooldown', function () {
    Carbon::setTestNow('2026-08-17');

    ['program' => $program, 'beneficiary' => $beneficiary, 'user' => $user] = createEligibilityFixtures();

    ProgramEligibilityRule::query()->create([
        'program_id' => $program->id,
        'cooldown_days' => 90,
    ]);

    Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => '2026-07-01',
        'date_delivered' => '2026-07-15',
        'was_delivered' => true,
        'user_id' => $user->id,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)(
        $program->fresh(),
        $beneficiary,
        [],
        Carbon::parse('2026-08-17'),
    );

    expect(collect($findings)->pluck('code')->all())->toContain('cooldown');
});

test('it does not warn after the cooldown window', function () {
    ['program' => $program, 'beneficiary' => $beneficiary, 'user' => $user] = createEligibilityFixtures();

    ProgramEligibilityRule::query()->create([
        'program_id' => $program->id,
        'cooldown_days' => 30,
    ]);

    Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => '2026-01-01',
        'date_delivered' => '2026-01-01',
        'was_delivered' => true,
        'user_id' => $user->id,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)(
        $program->fresh(),
        $beneficiary,
        [],
        Carbon::parse('2026-03-01'),
    );

    expect(collect($findings)->pluck('code')->all())->not->toContain('cooldown');
});

test('it warns when requested quantity plus released quantity exceeds the yearly cap', function () {
    ['program' => $program, 'beneficiary' => $beneficiary, 'item' => $item, 'user' => $user] = createEligibilityFixtures();

    ProgramItemCap::query()->create([
        'program_id' => $program->id,
        'item_id' => $item->id,
        'max_released_per_year' => 5,
    ]);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => '2026-03-01',
        'date_delivered' => '2026-03-02',
        'was_delivered' => true,
        'user_id' => $user->id,
    ]);

    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 4,
        'is_received' => true,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)(
        $program->fresh(['itemCaps.item']),
        $beneficiary,
        [['item_id' => $item->id, 'quantity' => 2]],
        Carbon::parse('2026-08-17'),
    );

    expect(collect($findings)->pluck('code')->all())->toContain('item_cap');
});

test('it ignores unreceived item quantities when applying yearly caps', function () {
    ['program' => $program, 'beneficiary' => $beneficiary, 'item' => $item, 'user' => $user] = createEligibilityFixtures();

    ProgramItemCap::query()->create([
        'program_id' => $program->id,
        'item_id' => $item->id,
        'max_released_per_year' => 2,
    ]);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => '2026-03-01',
        'was_delivered' => false,
        'user_id' => $user->id,
    ]);

    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'is_received' => false,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)(
        $program->fresh(['itemCaps.item']),
        $beneficiary,
        [['item_id' => $item->id, 'quantity' => 1]],
        Carbon::parse('2026-08-17'),
        $assistance->id,
    );

    expect(collect($findings)->pluck('code')->all())->not->toContain('item_cap');
});

test('it applies item caps within the calendar year', function (string $deliveredAt, string $asOf, bool $shouldWarn) {
    ['program' => $program, 'beneficiary' => $beneficiary, 'item' => $item, 'user' => $user] = createEligibilityFixtures();

    ProgramItemCap::query()->create([
        'program_id' => $program->id,
        'item_id' => $item->id,
        'max_released_per_year' => 2,
    ]);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => $deliveredAt,
        'date_delivered' => $deliveredAt,
        'was_delivered' => true,
        'user_id' => $user->id,
    ]);

    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'is_received' => true,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)(
        $program->fresh(['itemCaps.item']),
        $beneficiary,
        [['item_id' => $item->id, 'quantity' => 1]],
        Carbon::parse($asOf),
    );

    if ($shouldWarn) {
        expect(collect($findings)->pluck('code')->all())->toContain('item_cap');
    } else {
        expect(collect($findings)->pluck('code')->all())->not->toContain('item_cap');
    }
})->with([
    'same year counts' => ['2026-01-15', '2026-12-31', true],
    'previous year ignored' => ['2025-12-31', '2026-01-01', false],
]);

test('it ignores demographic rules for organization programs', function () {
    ['program' => $program, 'beneficiary' => $beneficiary] = createEligibilityFixtures();

    $program->update(['is_organization' => true]);

    ProgramEligibilityRule::query()->create([
        'program_id' => $program->id,
        'require_pwd' => true,
    ]);

    $findings = app(EvaluateAssistanceEligibility::class)($program->fresh(), $beneficiary);

    expect(collect($findings)->pluck('code')->all())->not->toContain('demographic_pwd');
});

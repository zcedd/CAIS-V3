<?php

use App\Enums\ItemKind;
use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramKind;
use App\Enums\RoleName;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Individual;
use App\Models\Item;
use App\Models\Program;
use App\Models\ProgramApprovalEvent;
use App\Models\User;
use App\Services\User\ProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('program cards sum budget cash and demographics including scheme batches', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $scheme = Program::factory()->scheme()->create([
        'department_id' => $department->id,
        'name' => 'Crisis Assistance',
    ]);
    $batch = Program::factory()->batch($scheme)->create();
    $fund = Fund::factory()->create([
        'department_id' => $department->id,
        'amount' => '15000',
    ]);
    $cash = Item::factory()->forDepartment($department)->cash()->create(['name' => 'Cash aid']);
    $goods = Item::factory()->forDepartment($department)->create([
        'name' => 'Rice',
        'kind' => ItemKind::Goods,
    ]);
    $batch->fund()->attach($fund->id);
    $batch->item()->attach([$cash->id, $goods->id]);

    $individual = Individual::factory()->create([
        'sex' => 'Female',
        'pwd' => true,
        'birthday' => now()->subYears(40)->toDateString(),
    ]);
    $beneficiary = $individual->beneficiaryRecord()->firstOrFail();
    $assistance = Assistance::query()->create([
        'program_id' => $batch->id,
        'beneficiary_id' => $beneficiary->id,
        'date_requested' => now()->toDateString(),
        'date_delivered' => now()->toDateString(),
        'was_delivered' => true,
        'user_id' => $user->id,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $cash->id,
        'quantity' => 3000,
        'is_received' => true,
    ]);
    AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $goods->id,
        'quantity' => 2,
        'is_received' => true,
    ]);
    ProgramApprovalEvent::query()->create([
        'program_id' => $batch->id,
        'user_id' => $user->id,
        'action' => ProgramApprovalAction::Submitted,
    ]);

    $metrics = app(ProgramService::class)->cardMetrics(collect([$scheme]))[$scheme->id];

    expect($metrics['budget'])->toBe(15000.0)
        ->and($metrics['cash_released'])->toBe(3000)
        ->and($metrics['goods_released'])->toBe(2)
        ->and($metrics['item_kinds'])->toContain(ItemKind::Cash->value, ItemKind::Goods->value)
        ->and($metrics['total_requests'])->toBe(1)
        ->and($metrics['delivered_requests'])->toBe(1)
        ->and($metrics['claimants'])->toBe(1)
        ->and($metrics['female'])->toBe(1)
        ->and($metrics['male'])->toBe(0)
        ->and($metrics['pwd'])->toBe(1)
        ->and($metrics['mean_age'])->toBe(40.0)
        ->and($metrics['submitted_at'])->not->toBeNull();
});

test('the endorsement trail includes returns and remarks', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::factory()->standalone()->create([
        'department_id' => $department->id,
    ]);

    ProgramApprovalEvent::query()->create([
        'program_id' => $program->id,
        'user_id' => $user->id,
        'action' => ProgramApprovalAction::Submitted,
        'remark' => 'Ready for review.',
    ]);
    ProgramApprovalEvent::query()->create([
        'program_id' => $program->id,
        'user_id' => $user->id,
        'action' => ProgramApprovalAction::Returned,
        'remark' => 'Add the household list.',
    ]);

    $trail = app(ProgramService::class)->cardMetrics(collect([$program]))[$program->id]['approval_trail'];

    expect($trail)->toHaveCount(2)
        ->and($trail[0]['action'])->toBe(ProgramApprovalAction::Submitted->value)
        ->and($trail[0]['remark'])->toBe('Ready for review.')
        ->and($trail[1]['action'])->toBe(ProgramApprovalAction::Returned->value)
        ->and($trail[1]['label'])->toBe('Returned')
        ->and($trail[1]['remark'])->toBe('Add the household list.');
});

test('department program analytics returns the summary and forbids another department', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $other = Department::create(['name' => 'Veterinary']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $outsider = User::factory()->create(['department_id' => $other->id]);
    $program = Program::factory()->standalone()->create([
        'department_id' => $department->id,
        'kind' => ProgramKind::Standalone,
    ]);

    $this->actingAs($outsider)
        ->getJson(route('user.programs.analytics', [$department, $program]))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('user.programs.analytics', [$department, $program]))
        ->assertOk()
        ->assertJsonStructure([
            'summary' => ['beneficiaries', 'avg_age', 'pwd', 'seniors', 'budget_burn'],
            'gender' => ['female', 'male', 'other'],
            'vulnerability',
            'age_brackets',
            'municipalities',
            'roster' => ['data', 'total'],
        ]);
});

test('an executive can read program analytics', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    seedRolesAndPermissions();
    $governor = User::factory()->create([
        'department_id' => Department::create(['name' => 'Governor'])->id,
    ]);
    $governor->syncRoles([RoleName::Executive->value]);
    $program = Program::factory()->standalone()->create([
        'department_id' => $department->id,
    ]);

    $this->actingAs($governor)
        ->getJson(route('executive.programs.analytics', $program))
        ->assertOk()
        ->assertJsonPath('summary.beneficiaries', 0);
});

test('the program page defers card metrics for that program', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::factory()->standalone()->create([
        'department_id' => $department->id,
    ]);
    $fund = Fund::factory()->create([
        'department_id' => $department->id,
        'amount' => '15000',
    ]);
    $program->fund()->attach($fund->id);

    $individual = Individual::factory()->create();
    Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/programs/show')
            ->loadDeferredProps('kpis', fn (Assert $reload) => $reload
                ->where('program_metrics.budget', 15000)
                ->where('program_metrics.total_requests', 1)));
});

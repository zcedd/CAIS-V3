<?php

use App\Enums\DistributionApprovalTaskStatus;
use App\Enums\DistributionBatchStatus;
use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramApprovalStatus;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DistributionApprovalPolicy;
use App\Models\Program;
use App\Models\ProgramApprovalEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

test('an administrator endorses a submitted program and can return it', function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = User::factory()->create(['department_id' => $department->id]);
    $head->syncRoles([RoleName::DepartmentHead->value]);
    $administrator = User::factory()->create(['department_id' => null]);
    $administrator->syncRoles([RoleName::Administrator->value]);
    $governor = User::factory()->create([
        'department_id' => Department::create(['name' => 'Office Of The Governor'])->id,
    ]);
    $governor->syncRoles([RoleName::Executive->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $program = Program::factory()->create([
        'name' => 'Relief Program',
        'department_id' => $department->id,
        'approval_status' => ProgramApprovalStatus::Draft,
    ]);

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect();

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingAdministrator);

    $this->actingAs($governor)
        ->post(route('executive.programs.approve', $program))
        ->assertForbidden();

    $this->actingAs($administrator)
        ->get(route('executive.programs.index'))
        ->assertForbidden();

    $this->actingAs($administrator)
        ->post(route('administrator.programs.endorse', $program), [
            'remark' => 'Ready for the executive.',
        ])
        ->assertRedirect();

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingGovernor)
        ->and(ProgramApprovalEvent::query()
            ->where('program_id', $program->id)
            ->where('action', ProgramApprovalAction::Endorsed)
            ->exists())->toBeTrue();

    $this->actingAs($administrator)
        ->get(route('administrator.programs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('executive/programs/index')
            ->where('review.mode', 'endorse')
            ->where('programs.data.0.show_url', route('administrator.programs.show', $program)));

    $returned = Program::factory()->create([
        'name' => 'Returned relief',
        'department_id' => $department->id,
        'approval_status' => ProgramApprovalStatus::Draft,
    ]);

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $returned]))
        ->assertRedirect();

    $this->actingAs($administrator)
        ->post(route('administrator.programs.return', $returned), [
            'remark' => 'Add the household list.',
        ])
        ->assertRedirect();

    expect($returned->refresh()->approval_status)->toBe(ProgramApprovalStatus::Returned);
});

test('a default distribution policy asks the administrator before the executive', function () {
    $fixture = distributionFixture();
    DistributionApprovalPolicy::query()
        ->where('program_id', $fixture['program']->id)
        ->delete();

    $administrator = User::factory()->create(['department_id' => null]);
    $administrator->syncRoles([RoleName::Administrator->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    $tasks = $batch->tasks()->with('stage')->orderBy('id')->get();

    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]->stage?->role_name)->toBe(RoleName::Administrator->value)
        ->and($tasks[0]->status)->toBe(DistributionApprovalTaskStatus::Pending)
        ->and($tasks[1]->stage?->role_name)->toBe(RoleName::Executive->value)
        ->and($tasks[1]->status)->toBe(DistributionApprovalTaskStatus::Waiting);

    $this->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertForbidden();

    $this->actingAs($administrator)
        ->get(route('administrator.distributions.show', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('executive/distributions/show')
            ->where('actions.can_finalize', false)
            ->where('actions.can_decide_items', false));

    $this->actingAs($administrator)
        ->post(route('administrator.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DistributionBatchStatus::ForApproval)
        ->and($tasks[1]->refresh()->status)->toBe(DistributionApprovalTaskStatus::Pending);

    $this->actingAs($fixture['governor'])
        ->get(route('executive.distributions.show', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('actions.can_finalize', true)
            ->where('actions.can_decide_items', true));

    $this->actingAs($fixture['governor'])
        ->post(route('executive.distributions.decide', $batch), ['decision' => 'approved'])
        ->assertRedirect();

    expect($batch->refresh()->status)->toBe(DistributionBatchStatus::Approved);
});

test('the seeder adds an administrator stage to a single-stage default policy', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $policy = DistributionApprovalPolicy::query()->create([
        'name' => 'Default executive approval',
        'department_id' => $department->id,
        'strategy' => 'sequential',
        'is_active' => true,
    ]);
    $policy->stages()->create([
        'sequence' => 1,
        'name' => 'Executive approval',
        'role_name' => RoleName::Executive->value,
    ]);

    $this->seed(RolePermissionSeeder::class);

    $stages = $policy->fresh()?->stages()->orderBy('sequence')->get();

    expect($stages)->toHaveCount(2)
        ->and($stages?->first()?->role_name)->toBe(RoleName::Administrator->value)
        ->and($stages?->last()?->role_name)->toBe(RoleName::Executive->value)
        ->and($stages?->last()?->sequence)->toBe(2);
});

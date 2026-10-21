<?php

use App\Enums\RoleName;
use App\Models\DistributionApprovalPolicy;
use App\Models\DistributionApprovalTask;
use App\Models\User;
use App\Services\Distribution\DistributionApprovalPolicyResolver;

test('updating a policy keeps stages that already have approval tasks', function () {
    $fixture = distributionFixture();
    $admin = distributionPolicyAdmin($fixture['department']->id);

    test()->actingAs($admin)
        ->post(route('admin.distribution-policies.store'), distributionPolicyPayload($fixture))
        ->assertRedirect();

    $policy = DistributionApprovalPolicy::query()->latest('id')->firstOrFail();
    $stage = $policy->stages()->firstOrFail();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    expect($batch->fresh()->distribution_approval_policy_id)->toBe($policy->id)
        ->and(DistributionApprovalTask::query()->where('distribution_approval_stage_id', $stage->id)->exists())->toBeTrue();

    test()->actingAs($admin)
        ->put(route('admin.distribution-policies.update', $policy), distributionPolicyPayload($fixture, [
            'name' => 'Updated office policy',
            'stages' => [[
                'id' => $stage->id,
                'name' => 'Governor review',
                'sequence' => 1,
                'role_name' => RoleName::Executive->value,
            ]],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($policy->fresh()->name)->toBe('Updated office policy')
        ->and($stage->fresh()->name)->toBe('Governor review')
        ->and(DistributionApprovalTask::query()->where('distribution_approval_stage_id', $stage->id)->exists())->toBeTrue();
});

test('removing a stage that has approval tasks is rejected', function () {
    $fixture = distributionFixture();
    $admin = distributionPolicyAdmin($fixture['department']->id);

    test()->actingAs($admin)
        ->post(route('admin.distribution-policies.store'), distributionPolicyPayload($fixture))
        ->assertRedirect();

    $policy = DistributionApprovalPolicy::query()->latest('id')->firstOrFail();
    $stage = $policy->stages()->firstOrFail();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($admin)
        ->put(route('admin.distribution-policies.update', $policy), distributionPolicyPayload($fixture, [
            'stages' => [[
                'name' => 'Replacement',
                'sequence' => 1,
                'role_name' => RoleName::DepartmentHead->value,
            ]],
        ]))
        ->assertSessionHasErrors([
            'stages' => 'A stage that already has approval tasks cannot be removed.',
        ]);

    expect($stage->fresh())->not->toBeNull()
        ->and($policy->fresh()->stages)->toHaveCount(1)
        ->and(DistributionApprovalTask::query()->where('distribution_approval_stage_id', $stage->id)->exists())->toBeTrue();
});

test('replacing an unused approval stage succeeds', function () {
    $fixture = distributionFixture();
    $admin = distributionPolicyAdmin($fixture['department']->id);

    test()->actingAs($admin)
        ->post(route('admin.distribution-policies.store'), distributionPolicyPayload($fixture))
        ->assertRedirect();

    $policy = DistributionApprovalPolicy::query()->latest('id')->firstOrFail();
    $stage = $policy->stages()->firstOrFail();

    test()->actingAs($admin)
        ->put(route('admin.distribution-policies.update', $policy), distributionPolicyPayload($fixture, [
            'stages' => [[
                'name' => 'Department review',
                'sequence' => 1,
                'role_name' => RoleName::DepartmentHead->value,
            ]],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stage->fresh())->toBeNull()
        ->and($policy->fresh()->stages)->toHaveCount(1)
        ->and($policy->fresh()->stages->first()->name)->toBe('Department review');
});

test('a stage with approval tasks is marked so it cannot be removed', function () {
    $fixture = distributionFixture();
    $admin = distributionPolicyAdmin($fixture['department']->id);

    test()->actingAs($admin)
        ->post(route('admin.distribution-policies.store'), distributionPolicyPayload($fixture))
        ->assertRedirect();

    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($admin)
        ->get(route('admin.distribution-policies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/distribution-policies/index')
            ->has('policies', 2)
            ->where('policies.0.stages.0.has_tasks', true));
});

test('a guest cannot delete an approval policy', function () {
    $policy = DistributionApprovalPolicy::query()->create([
        'name' => 'Office policy',
        'strategy' => 'sequential',
    ]);

    test()->delete(route('admin.distribution-policies.destroy', $policy))
        ->assertRedirect(route('login'));

    expect($policy->fresh())->not->toBeNull();
});

test('a department user cannot delete an approval policy', function () {
    $fixture = distributionFixture();
    $policy = DistributionApprovalPolicy::query()->create([
        'name' => 'Office policy',
        'strategy' => 'sequential',
        'department_id' => $fixture['department']->id,
    ]);

    test()->actingAs($fixture['creator'])
        ->delete(route('admin.distribution-policies.destroy', $policy))
        ->assertForbidden();

    expect($policy->fresh())->not->toBeNull();
});

test('deleting a policy hides it and keeps the approval record on an existing batch', function () {
    $fixture = distributionFixture();
    $admin = distributionPolicyAdmin($fixture['department']->id);

    test()->actingAs($admin)
        ->post(route('admin.distribution-policies.store'), distributionPolicyPayload($fixture))
        ->assertRedirect();

    $policy = DistributionApprovalPolicy::query()->latest('id')->firstOrFail();
    $batch = storeDistribution($fixture);
    submitDistribution($fixture, $batch);

    test()->actingAs($admin)
        ->delete(route('admin.distribution-policies.destroy', $policy))
        ->assertRedirect();

    $batch->refresh();

    expect($batch->policy?->id)->toBe($policy->id)
        ->and($batch->policy->trashed())->toBeTrue();

    test()->actingAs($admin)
        ->get(route('admin.distribution-policies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('policies', 1));

    $resolved = app(DistributionApprovalPolicyResolver::class)->resolve($fixture['program'], '500.00');

    expect($resolved->id)->not->toBe($policy->id);
    test()->assertSoftDeleted($policy);
});

function distributionPolicyAdmin(int $departmentId): User
{
    $admin = User::factory()->create(['department_id' => $departmentId]);
    assignSuperAdminRole($admin);

    return $admin;
}

/**
 * @param  array<string, mixed>  $fixture
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function distributionPolicyPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'name' => 'Office policy',
        'department_id' => $fixture['department']->id,
        'program_id' => $fixture['program']->id,
        'strategy' => 'sequential',
        'stages' => [
            ['name' => 'Executive', 'sequence' => 1, 'role_name' => RoleName::Executive->value],
        ],
    ], $overrides);
}

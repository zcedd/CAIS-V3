<?php

use App\Actions\User\CreateProgramBatch;
use App\Enums\DistributionType;
use App\Enums\ProgramDistributionMode;
use App\Enums\RequestSubStatusCode;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\Program;
use App\Services\User\ProgramService;

test('a new program defaults to batch distribution and does not allow direct release', function () {
    $program = Program::factory()->create();

    expect($program->resolvedDistributionMode())->toBe(ProgramDistributionMode::Batch)
        ->and($program->resolvedDistributionMode()->allowsIndividual())->toBeFalse()
        ->and($program->resolvedDistributionMode()->allowsBatch())->toBeTrue();
});

test('an individual program cannot create a batch distribution', function () {
    $fixture = distributionFixture();
    $fixture['program']->update(['distribution_mode' => ProgramDistributionMode::Individual]);

    $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture))
        ->assertSessionHasErrors('program_id');

    expect(DistributionBatch::query()->count())->toBe(0);
});

test('a batch program cannot use direct release', function () {
    $fixture = distributionFixture();
    $fixture['program']->update(['distribution_mode' => ProgramDistributionMode::Batch]);

    $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture, 'beneficiary'))
        ->assertSessionHasErrors('program_id');

    expect(DistributionBatch::query()->count())->toBe(0);
});

test('a program configured for both modes can start either workflow', function () {
    $fixture = distributionFixture(requests: 2);
    $fixture['program']->update(['distribution_mode' => ProgramDistributionMode::Both]);

    $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture))
        ->assertSessionHasNoErrors();

    $batch = DistributionBatch::query()->latest('id')->firstOrFail();
    expect($batch->distribution_type)->toBe(DistributionType::Batch);

    $individual = distributionFixture();
    $individual['program']->update(['distribution_mode' => ProgramDistributionMode::Both]);

    $this->actingAs($individual['creator'])
        ->post(route('user.distributions.store', $individual['department']), distributionPayload($individual, 'beneficiary'))
        ->assertSessionHasNoErrors();

    expect(DistributionBatch::query()->whereKeyNot($batch->id)->latest('id')->firstOrFail()->distribution_type)
        ->toBe(DistributionType::Individual);
});

test('direct release cannot bypass approval of the assistance request', function () {
    $fixture = distributionFixture();
    $fixture['program']->update(['distribution_mode' => ProgramDistributionMode::Individual]);
    $fixture['assistances'][0]->update([
        'current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
    ]);

    $this->actingAs($fixture['creator'])
        ->post(
            route('user.distributions.store', $fixture['department']),
            distributionPayload($fixture, 'beneficiary'),
        )
        ->assertSessionHasErrors('assistance_ids');

    expect(DistributionBatch::query()->count())->toBe(0);
});

test('changing a program distribution mode leaves existing distribution records unchanged', function () {
    $fixture = distributionFixture();

    $this->actingAs($fixture['creator'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture))
        ->assertSessionHasNoErrors();

    $batch = DistributionBatch::query()->latest('id')->firstOrFail();
    expect($batch->distribution_type)->toBe(DistributionType::Batch);

    $fixture['program']->update(['distribution_mode' => ProgramDistributionMode::Individual]);

    expect($batch->refresh()->distribution_type)->toBe(DistributionType::Batch);
});

test('a program batch inherits the parent distribution mode and follows later changes', function () {
    $department = Department::create(['name' => 'Health '.uniqid()]);
    $scheme = Program::factory()->forDepartment($department)->scheme()->create([
        'distribution_mode' => ProgramDistributionMode::Individual,
    ]);

    $batch = app(CreateProgramBatch::class)($scheme, [
        'batch_name' => 'First release',
        'start_at' => now()->toDateString(),
        'fund_ids' => [],
    ]);

    expect($batch->resolvedDistributionMode())->toBe(ProgramDistributionMode::Individual);

    app(ProgramService::class)->update($scheme, [
        'name' => $scheme->name,
        'descriptions' => $scheme->descriptions,
        'start_at' => now()->toDateString(),
        'distribution_mode' => ProgramDistributionMode::Batch->value,
        'item_ids' => [],
    ]);

    expect($batch->refresh()->resolvedDistributionMode())->toBe(ProgramDistributionMode::Batch)
        ->and(DistributionBatch::query()->count())->toBe(0);
});

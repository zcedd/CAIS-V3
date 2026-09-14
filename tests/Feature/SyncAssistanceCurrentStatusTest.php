<?php

use App\Enums\RequestSubStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * @return array{in_progress: int, verified: int, delivered: int, closed: int}
 */
function seedAssistanceStatusCatalog(): array
{
    return [
        'in_progress' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'verified' => catalogReasonId(RequestSubStatusCode::Verified),
        'delivered' => catalogReasonId(RequestSubStatusCode::Delivered),
        'closed' => catalogReasonId(RequestSubStatusCode::Closed),
    ];
}

it('denormalizes current status, was_delivered, and milestone dates from status history', function () {
    $statuses = seedAssistanceStatusCatalog();

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

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-SYNC-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-05-01',
        'remark' => null,
        'user_id' => $user->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $statuses['in_progress'],
        'remark' => null,
        'recorded_at' => '2026-05-01 08:00:00',
    ]);

    $assistance->refresh();

    expect($assistance->current_request_sub_status_id)->toBe($statuses['in_progress'])
        ->and(Carbon::parse($assistance->current_status_recorded_at)->toDateTimeString())->toBe('2026-05-01 08:00:00')
        ->and((bool) $assistance->was_delivered)->toBeFalse()
        ->and($assistance->date_delivered)->toBeNull();

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $statuses['verified'],
        'remark' => null,
        'recorded_at' => '2026-05-10 09:30:00',
    ]);

    $assistance->refresh();

    expect($assistance->current_request_sub_status_id)->toBe($statuses['verified'])
        ->and((bool) $assistance->was_delivered)->toBeFalse()
        ->and($assistance->date_delivered)->toBeNull();

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $statuses['delivered'],
        'remark' => null,
        'recorded_at' => '2026-05-15 14:00:00',
    ]);

    $assistance->refresh();

    expect($assistance->current_request_sub_status_id)->toBe($statuses['delivered'])
        ->and((bool) $assistance->was_delivered)->toBeTrue()
        ->and($assistance->date_delivered)->toBe('2026-05-15');
});

it('keeps was_delivered true after a later non-delivered status is recorded', function () {
    $statuses = seedAssistanceStatusCatalog();

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

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-SYNC-002',
        'name' => 'Maria Santos',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 2,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-05-01',
        'user_id' => $user->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $statuses['delivered'],
        'recorded_at' => '2026-05-15 10:00:00',
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $statuses['closed'],
        'recorded_at' => '2026-05-20 10:00:00',
    ]);

    $assistance->refresh();

    expect((bool) $assistance->was_delivered)->toBeTrue()
        ->and($assistance->date_delivered)->toBe('2026-05-15')
        ->and($assistance->current_request_sub_status_id)->toBe($statuses['closed']);
});

it('adds current status columns and drops legacy beneficiary and milestone date columns', function () {
    expect(Schema::hasColumn('assistances', 'current_request_sub_status_id'))->toBeTrue()
        ->and(Schema::hasColumn('assistances', 'current_status_recorded_at'))->toBeTrue()
        ->and(Schema::hasColumn('assistances', 'was_delivered'))->toBeTrue()
        ->and(Schema::hasColumn('assistances', 'individual_id'))->toBeFalse()
        ->and(Schema::hasColumn('assistances', 'organization_id'))->toBeFalse()
        ->and(Schema::hasColumn('assistances', 'date_verified'))->toBeFalse()
        ->and(Schema::hasColumn('assistances', 'date_denied'))->toBeFalse()
        ->and(Schema::hasColumn('assistances', 'date_delivered'))->toBeTrue();
});

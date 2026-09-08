<?php

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use App\Support\RequestSubStatusCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createBulkStatusAssistance(
    Program $program,
    User $user,
    string $caisNumber,
    string $name,
): Assistance {
    $beneficiary = Beneficiary::create([
        'cais_number' => $caisNumber,
        'name' => $name,
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => crc32($caisNumber),
    ]);

    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    return Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-05-01',
        'remark' => null,
        'user_id' => $user->id,
        'assigned_to_id' => $user->id,
        'assigned_at' => now(),
    ]);
}

test('authenticated users can bulk update assistance status for their department program', function () {
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

    $firstAssistance = createBulkStatusAssistance($program, $user, 'CAIS-001', 'Juan Dela Cruz');
    $secondAssistance = createBulkStatusAssistance($program, $user, 'CAIS-002', 'Maria Santos');

    $inProgressSubStatusId = catalogReasonId(RequestSubStatusCode::AwaitingReview);
    $verifiedSubStatusId = catalogReasonId(RequestSubStatusCode::Verified);

    expect($inProgressSubStatusId)->not->toBeNull()
        ->and($verifiedSubStatusId)->not->toBeNull();

    foreach ([$firstAssistance, $secondAssistance] as $assistance) {
        AssistanceRequestSubStatus::query()->create([
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $inProgressSubStatusId,
            'remark' => null,
            'recorded_at' => '2026-05-01 00:00:00',
        ]);
    }

    $programShowUrl = route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]);

    $response = $this->actingAs($user)
        ->from($programShowUrl)
        ->patch(
            route('user.programs.assistances.status.bulk-update', [
                'department' => $department->slug,
                'program' => $program->id,
            ]),
            [
                'assistance_ids' => [$firstAssistance->id, $secondAssistance->id],
                'request_sub_status_id' => $verifiedSubStatusId,
                'recorded_at' => '2026-05-10',
                'remark' => 'Bulk verified after review',
            ],
        );

    $response->assertRedirect($programShowUrl);

    foreach ([$firstAssistance, $secondAssistance] as $assistance) {
        $latestSubStatus = AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->where('request_sub_status_id', $verifiedSubStatusId)
            ->latest('recorded_at')
            ->first();

        expect($latestSubStatus)->not->toBeNull()
            ->and($latestSubStatus->remark)->toBe('Bulk verified after review')
            ->and(Carbon::parse($latestSubStatus->recorded_at)->toDateString())->toBe('2026-05-10');
    }
});

test('bulk status update preserves the recorded at time', function () {
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

    $firstAssistance = createBulkStatusAssistance($program, $user, 'CAIS-003', 'Ana Reyes');
    $secondAssistance = createBulkStatusAssistance($program, $user, 'CAIS-004', 'Pedro Cruz');

    $inProgressSubStatusId = catalogReasonId(RequestSubStatusCode::AwaitingReview);
    $verifiedSubStatusId = catalogReasonId(RequestSubStatusCode::Verified);

    foreach ([$firstAssistance, $secondAssistance] as $assistance) {
        AssistanceRequestSubStatus::query()->create([
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $inProgressSubStatusId,
            'remark' => null,
            'recorded_at' => '2026-05-01 00:00:00',
        ]);
    }

    $this->actingAs($user)->patch(
        route('user.programs.assistances.status.bulk-update', [
            'department' => $department->slug,
            'program' => $program->id,
        ]),
        [
            'assistance_ids' => [$firstAssistance->id, $secondAssistance->id],
            'request_sub_status_id' => $verifiedSubStatusId,
            'recorded_at' => '2026-05-10 14:30:00',
            'remark' => 'Bulk verified in the afternoon',
        ],
    )->assertRedirect();

    foreach ([$firstAssistance, $secondAssistance] as $assistance) {
        $latestSubStatus = AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->where('request_sub_status_id', $verifiedSubStatusId)
            ->latest('recorded_at')
            ->first();

        expect($latestSubStatus)->not->toBeNull()
            ->and(Carbon::parse($latestSubStatus->recorded_at)->toDateTimeString())->toBe('2026-05-10 14:30:00');
    }
});

test('bulk status update rejects delivered sub-status', function () {
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

    $assistance = createBulkStatusAssistance($program, $user, 'CAIS-001', 'Juan Dela Cruz');

    $deliveredSubStatusId = catalogReasonId(RequestSubStatusCode::Delivered);

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]))->patch(
        route('user.programs.assistances.status.bulk-update', [
            'department' => $department->slug,
            'program' => $program->id,
        ]),
        [
            'assistance_ids' => [$assistance->id],
            'request_sub_status_id' => $deliveredSubStatusId,
            'recorded_at' => '2026-05-15',
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));
    $response->assertSessionHasErrors('request_sub_status_id');
});

test('users cannot bulk update assistance records from another department program', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $assistance = createBulkStatusAssistance($program, $user, 'CAIS-001', 'Juan Dela Cruz');

    $verifiedSubStatusId = catalogReasonId(RequestSubStatusCode::Verified);

    $response = $this->actingAs($user)->patch(
        route('user.programs.assistances.status.bulk-update', [
            'department' => $departmentB->slug,
            'program' => $program->id,
        ]),
        [
            'assistance_ids' => [$assistance->id],
            'request_sub_status_id' => $verifiedSubStatusId,
            'recorded_at' => '2026-05-10',
        ],
    );

    $response->assertForbidden();
});

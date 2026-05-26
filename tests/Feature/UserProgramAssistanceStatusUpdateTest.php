<?php

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestSubStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('authenticated users can update assistance status for their department program', function () {
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
        'cais_number' => 'CAIS-001',
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

    $inProgressSubStatusId = RequestSubStatus::query()
        ->where('name', 'In Progress')
        ->value('id');

    $verifiedSubStatusId = RequestSubStatus::query()
        ->where('name', 'Verified')
        ->value('id');

    expect($inProgressSubStatusId)->not->toBeNull()
        ->and($verifiedSubStatusId)->not->toBeNull();

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $inProgressSubStatusId,
        'remark' => null,
        'recorded_at' => '2026-05-01 00:00:00',
    ]);

    $response = $this->actingAs($user)->patch(
        route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]),
        [
            'request_sub_status_id' => $verifiedSubStatusId,
            'recorded_at' => '2026-05-10',
            'remark' => 'Verified after review',
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $latestSubStatus = AssistanceRequestSubStatus::query()
        ->where('assistance_id', $assistance->id)
        ->where('request_sub_status_id', $verifiedSubStatusId)
        ->latest('recorded_at')
        ->first();

    expect($latestSubStatus)->not->toBeNull()
        ->and($latestSubStatus->remark)->toBe('Verified after review')
        ->and(Carbon::parse($latestSubStatus->recorded_at)->toDateString())->toBe('2026-05-10');
});

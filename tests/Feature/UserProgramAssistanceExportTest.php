<?php

use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function assignDownloadAssistancePermission(User $user): void
{
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'Download Assistance',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $roleId = DB::table('roles')->insertGetId([
        'name' => 'head',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('role_has_permissions')->insert([
        'permission_id' => $permissionId,
        'role_id' => $roleId,
    ]);

    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_type' => 'App\\Models\\User',
        'model_id' => $user->id,
    ]);
}

test('user with download assistance permission can export filtered assistances as csv', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    assignDownloadAssistancePermission($user);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);
    $requestStatus = RequestStatus::create(['name' => 'Submitted']);
    $matchingSubStatus = RequestSubStatus::create([
        'name' => 'In Progress',
        'request_status_id' => $requestStatus->id,
    ]);
    $otherStatus = RequestStatus::create(['name' => 'Denied']);
    $otherSubStatus = RequestSubStatus::create([
        'name' => 'Not Eligible',
        'request_status_id' => $otherStatus->id,
    ]);

    $firstBeneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);

    $secondBeneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-002',
        'name' => 'Maria Santos',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 2,
    ]);

    $matchingAssistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $firstBeneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $otherAssistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $secondBeneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    AssistanceRequestSubStatus::create([
        'assistance_id' => $matchingAssistance->id,
        'request_sub_status_id' => $matchingSubStatus->id,
        'recorded_at' => now(),
    ]);

    AssistanceRequestSubStatus::create([
        'assistance_id' => $otherAssistance->id,
        'request_sub_status_id' => $otherSubStatus->id,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('user.programs.assistances.export', [
        'department' => $department->slug,
        'program' => $program->id,
        'format' => 'csv',
        'status' => ['Submitted'],
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('CAIS-001')
        ->not->toContain('CAIS-002');
});

test('user without download assistance permission cannot export assistances', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    DB::table('permissions')->insert([
        'name' => 'Download Assistance',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
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

    $this->actingAs($user)
        ->get(route('user.programs.assistances.export', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->assertForbidden();
});

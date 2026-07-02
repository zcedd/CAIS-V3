<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view a user program show page', function () {
    $department = Department::create(['name' => 'Department A']);
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $response = $this->get(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view a program in their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $response = $this->actingAs($user)->get(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('user/programs/show')
        ->where('program.id', $program->id)
        ->where('program.name', 'Alpha Program')
        ->where('program.descriptions', 'Full description')
        ->where('department.id', $department->id)
        ->where('department.slug', $department->slug)
        ->has('assistances.data', 0));
});

test('program show page includes assistances for the program', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $beneficiaryId = DB::table('beneficiaries')->insertGetId([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => Individual::class,
        'beneficiable_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiaryId,
        'mode_of_request_id' => null,
        'date_requested' => now()->toDateString(),
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => null,
        'user_id' => $user->id,
        'remark' => 'Follow up next week',
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'specification' => '25 kg',
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assistances.data', 1)
            ->where('assistances.data.0.cais_number', 'CAIS-001')
            ->where('assistances.data.0.beneficiary_name', 'Juan Dela Cruz')
            ->where('assistances.data.0.status', 'Pending')
            ->where('assistances.data.0.remark', 'Follow up next week')
            ->where('assistances.data.0.items.0.name', 'Rice')
            ->where('assistances.data.0.items.0.quantity', 2)
            ->where('assistances.data.0.items.0.unit', 'kg')
            ->where('assistances.data.0.items.0.specification', '25 kg'));
});

test('authenticated users cannot view a program show page for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $program = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'For B',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $departmentB->slug,
            'program' => $program->id,
        ]))
        ->assertForbidden();
});

test('program show page accepts sort direction and per page query parameters', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    Assistance::create([
        'program_id' => $program->id,
        'date_requested' => '2024-01-01',
        'user_id' => $user->id,
    ]);

    Assistance::create([
        'program_id' => $program->id,
        'date_requested' => '2024-06-01',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'sort' => 'date_requested',
            'direction' => 'asc',
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'date_requested')
            ->where('direction', 'asc')
            ->where('per_page', 10)
            ->where('assistances.per_page', 10)
            ->where('assistances.data.0.date_requested', '2024-01-01')
            ->where('assistances.data.1.date_requested', '2024-06-01'));
});

test('program show page uses latest request sub status for assistance status', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => '2024-01-01',
        'date_verified' => '2024-02-01',
        'user_id' => $user->id,
        'remark' => 'verified record',
    ]);

    $requestStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $olderSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $requestStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $latestSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Verified',
        'request_status_id' => $requestStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('assistance_request_sub_status')->insert([
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $olderSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-01-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $latestSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assistances.data', 1)
            ->where('assistances.data.0.request_sub_status', 'Verified')
            ->where('assistances.data.0.request_status', 'Submitted')
            ->where(
                'assistances.data.0.request_sub_status_recorded_at',
                fn (mixed $value): bool => is_string($value)
                    && str_contains($value, '2024-02-02T10:00:00'),
            )
            ->where('assistances.data.0.status', 'Verified'));
});

test('program show page uses highest id when multiple sub statuses share the same recorded at', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => '2024-01-01',
        'user_id' => $user->id,
    ]);

    $requestStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $olderSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $requestStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $latestSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Verified',
        'request_status_id' => $requestStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('assistance_request_sub_status')->insert([
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $olderSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $latestSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assistances.data', 1)
            ->where('assistances.data.0.request_sub_status', 'Verified')
            ->where('assistances.data.0.status', 'Verified'));
});

test('program show page filters assistances by request status on the server', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $submittedStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $verificationStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Verification',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $submittedSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $submittedStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $verifiedSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Verified',
        'request_status_id' => $verificationStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $submittedAssistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => '2024-01-01',
        'user_id' => $user->id,
        'remark' => 'submitted record',
    ]);

    $verifiedAssistance = Assistance::create([
        'program_id' => $program->id,
        'date_verified' => '2024-02-01',
        'user_id' => $user->id,
        'remark' => 'verified record',
    ]);

    DB::table('assistance_request_sub_status')->insert([
        [
            'assistance_id' => $submittedAssistance->id,
            'request_sub_status_id' => $submittedSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-01-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'assistance_id' => $verifiedAssistance->id,
            'request_sub_status_id' => $verifiedSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'status' => ['Submitted'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', ['Submitted'])
            ->has('status_options', 2)
            ->has('assistances.data', 1)
            ->where('assistances.data.0.request_status', 'Submitted')
            ->where('assistances.data.0.remark', 'submitted record'));
});

test('authenticated users cannot view a program from another department using their own department slug', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $programInB = Program::create([
        'name' => 'Other dept program',
        'descriptions' => '',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.show', [
            'department' => $departmentA->slug,
            'program' => $programInB->id,
        ]))
        ->assertNotFound();
});

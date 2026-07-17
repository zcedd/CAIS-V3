<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Deferred Inertia props are only resolved on partial reloads, so request them explicitly.
 *
 * @param  list<string>  $props
 * @param  array<string, mixed>  $query
 */
function getProgramShowPartial(User $user, string $departmentSlug, int $programId, array $props, array $query = [])
{
    return test()->actingAs($user)->get(
        route('user.programs.show', array_merge([
            'department' => $departmentSlug,
            'program' => $programId,
        ], $query)),
        [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'user/programs/show',
            'X-Inertia-Partial-Data' => implode(',', $props),
        ],
    );
}

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
        ->where('department.slug', $department->slug));

    getProgramShowPartial($user, $department->slug, $program->id, ['summary', 'assistances'])
        ->assertOk()
        ->assertJsonPath('props.summary.total_requests', 0)
        ->assertJsonPath('props.summary.delivered_requests', 0)
        ->assertJsonPath('props.summary.in_progress_requests', 0)
        ->assertJsonPath('props.summary.total_delivered_items', 0)
        ->assertJsonCount(0, 'props.assistances.data');
});

test('program show page summary reflects assistances for the program only', function () {
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

    $otherProgram = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'Other program',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $inProgressBeneficiaryId = DB::table('beneficiaries')->insertGetId([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => Individual::class,
        'beneficiable_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $inProgressAssistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $inProgressBeneficiaryId,
        'mode_of_request_id' => null,
        'date_requested' => now()->toDateString(),
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => null,
        'user_id' => $user->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $inProgressAssistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $deliveredBeneficiaryId = DB::table('beneficiaries')->insertGetId([
        'cais_number' => 'CAIS-002',
        'name' => 'Maria Santos',
        'beneficiable_type' => Individual::class,
        'beneficiable_id' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $deliveredAssistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $deliveredBeneficiaryId,
        'mode_of_request_id' => null,
        'date_requested' => now()->toDateString(),
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $deliveredAssistance->id,
        'item_id' => $item->id,
        'quantity' => 3,
        'is_received' => true,
    ]);

    $otherBeneficiaryId = DB::table('beneficiaries')->insertGetId([
        'cais_number' => 'CAIS-003',
        'name' => 'Other Beneficiary',
        'beneficiable_type' => Individual::class,
        'beneficiable_id' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherAssistance = Assistance::create([
        'program_id' => $otherProgram->id,
        'beneficiary_id' => $otherBeneficiaryId,
        'mode_of_request_id' => null,
        'date_requested' => now()->toDateString(),
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $otherAssistance->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'is_received' => true,
    ]);

    getProgramShowPartial($user, $department->slug, $program->id, ['summary'])
        ->assertOk()
        ->assertJsonPath('component', 'user/programs/show')
        ->assertJsonPath('props.summary.total_requests', 2)
        ->assertJsonPath('props.summary.delivered_requests', 1)
        ->assertJsonPath('props.summary.in_progress_requests', 1)
        ->assertJsonPath('props.summary.total_delivered_items', 3);
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

    getProgramShowPartial($user, $department->slug, $program->id, ['assistances'])
        ->assertOk()
        ->assertJsonCount(1, 'props.assistances.data')
        ->assertJsonPath('props.assistances.data.0.cais_number', 'CAIS-001')
        ->assertJsonPath('props.assistances.data.0.beneficiary_name', 'Juan Dela Cruz')
        ->assertJsonPath('props.assistances.data.0.status', 'Pending')
        ->assertJsonPath('props.assistances.data.0.remark', 'Follow up next week')
        ->assertJsonPath('props.assistances.data.0.items.0.name', 'Rice')
        ->assertJsonPath('props.assistances.data.0.items.0.quantity', 2)
        ->assertJsonPath('props.assistances.data.0.items.0.unit', 'kg')
        ->assertJsonPath('props.assistances.data.0.items.0.specification', '25 kg');
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

    getProgramShowPartial($user, $department->slug, $program->id, ['sort', 'direction', 'per_page', 'assistances'], [
        'sort' => 'date_requested',
        'direction' => 'asc',
        'per_page' => 10,
    ])
        ->assertOk()
        ->assertJsonPath('props.sort', 'date_requested')
        ->assertJsonPath('props.direction', 'asc')
        ->assertJsonPath('props.per_page', 10)
        ->assertJsonPath('props.assistances.per_page', 10)
        ->assertJsonPath('props.assistances.data.0.date_requested', '2024-01-01')
        ->assertJsonPath('props.assistances.data.1.date_requested', '2024-06-01');
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
    ]);

    $olderSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $requestStatusId,
        'description' => null,
    ]);

    $latestSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Verified',
        'request_status_id' => $requestStatusId,
        'description' => null,
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

    $response = getProgramShowPartial($user, $department->slug, $program->id, ['assistances'])
        ->assertOk()
        ->assertJsonCount(1, 'props.assistances.data')
        ->assertJsonPath('props.assistances.data.0.request_sub_status', 'Verified')
        ->assertJsonPath('props.assistances.data.0.request_status', 'Submitted')
        ->assertJsonPath('props.assistances.data.0.status', 'Verified');

    $recordedAt = $response->json('props.assistances.data.0.request_sub_status_recorded_at');

    expect($recordedAt)->toBeString()->toContain('2024-02-02T10:00:00');
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
    ]);

    $olderSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $requestStatusId,
        'description' => null,
    ]);

    $latestSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Verified',
        'request_status_id' => $requestStatusId,
        'description' => null,
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

    getProgramShowPartial($user, $department->slug, $program->id, ['assistances'])
        ->assertOk()
        ->assertJsonCount(1, 'props.assistances.data')
        ->assertJsonPath('props.assistances.data.0.request_sub_status', 'Verified')
        ->assertJsonPath('props.assistances.data.0.status', 'Verified');
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
    ]);

    $verificationStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Verification',
    ]);

    $submittedSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $submittedStatusId,
        'description' => null,
    ]);

    $verifiedSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Verified',
        'request_status_id' => $verificationStatusId,
        'description' => null,
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

    getProgramShowPartial($user, $department->slug, $program->id, ['status', 'status_options', 'assistances'], [
        'status' => ['Submitted'],
    ])
        ->assertOk()
        ->assertJsonPath('props.status', ['Submitted'])
        ->assertJsonCount(2, 'props.status_options')
        ->assertJsonCount(1, 'props.assistances.data')
        ->assertJsonPath('props.assistances.data.0.request_status', 'Submitted')
        ->assertJsonPath('props.assistances.data.0.remark', 'submitted record');
});

test('program show page includes status breakdown, funding sources, and covered items', function () {
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

    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => 150000.50,
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $program->fund()->attach($fund->id);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program->item()->attach($item->id);

    Assistance::create([
        'program_id' => $program->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    Assistance::create([
        'program_id' => $program->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    Assistance::create([
        'program_id' => $program->id,
        'date_requested' => now()->toDateString(),
        'date_delivered' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    getProgramShowPartial($user, $department->slug, $program->id, [
        'status_breakdown',
        'program_funds',
        'program_covered_items',
    ])
        ->assertOk()
        ->assertJsonPath('component', 'user/programs/show')
        ->assertJsonCount(2, 'props.status_breakdown')
        ->assertJsonPath('props.status_breakdown.0.status', 'Pending')
        ->assertJsonPath('props.status_breakdown.0.count', 2)
        ->assertJsonPath('props.status_breakdown.1.status', 'Delivered')
        ->assertJsonPath('props.status_breakdown.1.count', 1)
        ->assertJsonCount(1, 'props.program_funds')
        ->assertJsonPath('props.program_funds.0.name', 'General Fund')
        ->assertJsonPath('props.program_funds.0.year', '2026')
        ->assertJsonPath('props.program_funds.0.amount', 150000.5)
        ->assertJsonCount(1, 'props.program_covered_items')
        ->assertJsonPath('props.program_covered_items.0.name', 'Rice')
        ->assertJsonPath('props.program_covered_items.0.unit', 'kg');
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
        ->assertForbidden();
});

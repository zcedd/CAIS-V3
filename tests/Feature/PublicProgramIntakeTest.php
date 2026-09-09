<?php

use App\Enums\RequestStatusCode;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\ProgramEligibilityRule;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{
 *     department: Department,
 *     user: User,
 *     program: Program,
 *     item: Item,
 *     barangayId: int
 * }
 */
function createPublicIntakeContext(array $programAttributes = []): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program = Program::factory()->standalone()->forDepartment($department)->create([
        'public_intake' => true,
        ...$programAttributes,
    ]);
    $program->fund()->attach($fund->id);
    $program->item()->attach($item->id);

    $barangayId = createAddressBarangay();

    seedPublicIntakeStatuses();
    ModeOfRequest::query()->firstOrCreate(['name' => 'Online']);
    ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    return compact('department', 'user', 'program', 'item', 'barangayId');
}

function seedPublicIntakeStatuses(): void
{
    $draftId = RequestStatus::query()->where('name', 'Draft')->value('id')
        ?? RequestStatus::query()->create(['name' => 'Draft'])->id;
    $submittedId = RequestStatus::query()->where('name', 'Submitted')->value('id')
        ?? RequestStatus::query()->create(['name' => 'Submitted'])->id;

    RequestSubStatus::query()->firstOrCreate(
        ['name' => 'Saved For Later'],
        ['request_status_id' => $draftId, 'description' => null],
    );
    RequestSubStatus::query()->firstOrCreate(
        ['name' => 'Awaiting Review'],
        ['request_status_id' => $submittedId, 'description' => null],
    );
    RequestSubStatus::query()->firstOrCreate(
        ['name' => 'In Progress'],
        ['request_status_id' => $draftId, 'description' => null],
    );
}

/**
 * @return array<string, mixed>
 */
function publicIntakeIdentityPayload(int $barangayId, array $overrides = []): array
{
    return [
        'first_name' => 'Juan',
        'middle_name' => 'Dela',
        'last_name' => 'Cruz',
        'birthday' => '1990-01-01',
        'sex' => 'Male',
        'address_barangay_id' => $barangayId,
        'pwd' => false,
        'is_4ps_beneficiary' => false,
        'is_solo_parent' => false,
        'indigenous' => false,
        ...$overrides,
    ];
}

test('staff can enable public intake on a standalone program', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createPublicIntakeContext([
        'public_intake' => false,
    ]);

    $this->actingAs($user)
        ->put(route('user.programs.update', [
            'department' => $department->slug,
            'program' => $program->id,
        ]), [
            'name' => $program->name,
            'descriptions' => $program->descriptions,
            'start_at' => now()->toDateString(),
            'fund_ids' => $program->fund()->pluck('funds.id')->all(),
            'item_ids' => [$item->id],
            'public_intake' => true,
        ])
        ->assertRedirect();

    expect($program->refresh()->public_intake)->toBeTrue();
});

test('staff cannot enable public intake on a parent program', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $scheme->item()->attach($item->id);

    $this->actingAs($user)
        ->put(route('user.programs.update', [
            'department' => $department->slug,
            'program' => $scheme->id,
        ]), [
            'name' => $scheme->name,
            'descriptions' => $scheme->descriptions,
            'start_at' => now()->toDateString(),
            'item_ids' => [$item->id],
            'public_intake' => true,
        ])
        ->assertSessionHasErrors('public_intake');
});

test('staff cannot enable public intake on an organization program', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createPublicIntakeContext([
        'public_intake' => false,
        'is_organization' => true,
    ]);

    $this->actingAs($user)
        ->put(route('user.programs.update', [
            'department' => $department->slug,
            'program' => $program->id,
        ]), [
            'name' => $program->name,
            'descriptions' => $program->descriptions,
            'start_at' => now()->toDateString(),
            'fund_ids' => $program->fund()->pluck('funds.id')->all(),
            'item_ids' => [$item->id],
            'is_organization' => true,
            'public_intake' => true,
        ])
        ->assertSessionHasErrors('public_intake');
});

test('staff can enable public intake on a batch', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $scheme->item()->attach($item->id);
    $batch = Program::factory()->batch($scheme)->create(['public_intake' => false]);
    $batch->fund()->attach($fund->id);
    $batch->item()->attach($item->id);

    $this->actingAs($user)
        ->put(route('user.programs.update', [
            'department' => $department->slug,
            'program' => $batch->id,
        ]), [
            'batch_name' => $batch->batch_name,
            'descriptions' => $batch->descriptions,
            'start_at' => now()->toDateString(),
            'fund_ids' => [$fund->id],
            'item_ids' => [$item->id],
            'public_intake' => true,
        ])
        ->assertRedirect();

    expect($batch->refresh()->public_intake)->toBeTrue()
        ->and($scheme->refresh()->public_intake)->toBeFalse();
});

test('public catalog lists only open individual programs with intake enabled', function () {
    ['program' => $open] = createPublicIntakeContext();
    $department = $open->department;

    Program::factory()->standalone()->forDepartment($department)->create([
        'name' => 'Disabled intake',
        'public_intake' => false,
    ]);
    Program::factory()->standalone()->forDepartment($department)->closed()->create([
        'name' => 'Closed public',
        'public_intake' => true,
    ]);
    Program::factory()->organization()->standalone()->forDepartment($department)->create([
        'name' => 'Org public',
        'public_intake' => true,
    ]);
    Program::factory()->scheme()->forDepartment($department)->create([
        'name' => 'Scheme',
        'public_intake' => true,
    ]);

    $this->get(route('public.apply.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/apply/index')
            ->where('departments', function ($departments) use ($open): bool {
                $groups = collect($departments);
                $programIds = $groups
                    ->flatMap(fn ($group): array => collect(is_array($group) ? ($group['programs'] ?? []) : [])->all())
                    ->pluck('id')
                    ->all();

                return $programIds === [$open->id];
            }));
});

test('public apply 404s when intake is disabled or the program is closed', function () {
    ['program' => $program] = createPublicIntakeContext(['public_intake' => false]);

    $this->get(route('public.apply.show', $program))->assertNotFound();

    $program->update(['public_intake' => true, 'is_closed' => true]);

    $this->get(route('public.apply.show', $program))->assertNotFound();
});

test('public submit creates a beneficiary and awaiting review assistance', function () {
    ['program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId),
        'intent' => 'submit',
        'consent' => true,
        'create_new' => true,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 2],
        ],
    ])->assertRedirect(route('public.apply.confirmation', $program));

    $individual = Individual::query()->where('first_name', 'Juan')->first();
    $assistance = Assistance::query()->first();

    expect($individual)->not->toBeNull()
        ->and($individual->cais_number)->toStartWith('PRO-')
        ->and($assistance)->not->toBeNull()
        ->and($assistance->user_id)->toBeNull()
        ->and($assistance->assigned_to_id)->toBeNull()
        ->and($assistance->program_id)->toBe($program->id)
        ->and($assistance->currentRequestSubStatus?->name)->toBe('Awaiting Review');

    expect(AssistanceItem::query()->where('assistance_id', $assistance->id)->value('quantity'))->toBe(2);
    expect(ModeOfRequest::query()->find($assistance->mode_of_request_id)?->name)->toBe('Online');
});

test('public submit with a submitted step owner assigns that owner', function () {
    ['department' => $department, 'program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();
    $maria = User::factory()->create(['department_id' => $department->id]);

    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
    $workflow->steps()
        ->where('request_status_id', catalogParentId(RequestStatusCode::Submitted))
        ->update([
            'assigned_to_id' => $maria->id,
            'requires_assignee' => true,
        ]);

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId),
        'intent' => 'submit',
        'consent' => true,
        'create_new' => true,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect(route('public.apply.confirmation', $program));

    expect(Assistance::query()->value('assigned_to_id'))->toBe($maria->id);
});

test('public submit can confirm a high score duplicate instead of creating a new profile', function () {
    ['program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();

    $individual = Individual::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'birthday' => '1990-01-01',
        'address_barangay_id' => $barangayId,
        'sex' => 'Male',
    ]);
    $beneficiary = Beneficiary::query()
        ->where('beneficiable_type', Individual::class)
        ->where('beneficiable_id', $individual->id)
        ->first();

    expect($beneficiary)->not->toBeNull();

    $this->postJson(route('public.apply.duplicates', $program), publicIntakeIdentityPayload($barangayId))
        ->assertOk()
        ->assertJsonPath('matches.0.id', $beneficiary->id);

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId),
        'intent' => 'submit',
        'consent' => true,
        'confirmed_beneficiary_id' => $beneficiary->id,
        'create_new' => false,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect();

    expect(Individual::query()->count())->toBe(1)
        ->and(Assistance::query()->value('beneficiary_id'))->toBe($beneficiary->id);
});

test('creating none of these still creates a new beneficiary when matches exist', function () {
    ['program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();

    Individual::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'birthday' => '1990-01-01',
        'address_barangay_id' => $barangayId,
        'sex' => 'Male',
    ]);

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId),
        'intent' => 'submit',
        'consent' => true,
        'create_new' => true,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect();

    expect(Individual::query()->count())->toBe(2);
});

test('public submit is blocked by hard and soft eligibility findings', function () {
    ['program' => $program, 'item' => $item, 'barangayId' => $barangayId, 'user' => $user] = createPublicIntakeContext();

    ProgramEligibilityRule::query()->create([
        'program_id' => $program->id,
        'require_pwd' => true,
        'require_4ps' => false,
        'require_solo_parent' => false,
        'require_indigenous' => false,
    ]);

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId, ['pwd' => false]),
        'intent' => 'submit',
        'consent' => true,
        'create_new' => true,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('eligibility');

    $program->eligibilityRule()->update(['require_pwd' => false]);

    $individual = Individual::factory()->create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'birthday' => '1985-05-05',
        'address_barangay_id' => $barangayId,
        'sex' => 'Female',
        'pwd' => false,
    ]);
    $beneficiary = Beneficiary::query()
        ->where('beneficiable_id', $individual->id)
        ->first();

    $mode = ModeOfRequest::query()->where('name', 'Walk In')->first();
    $inProgress = RequestSubStatus::query()->where('code', 'awaiting_review')->first()
        ?? RequestSubStatus::query()->where('name', 'Awaiting Review')->first();

    $open = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $open->id,
        'request_sub_status_id' => $inProgress->id,
        'recorded_at' => now(),
    ]);

    $this->post(route('public.apply.store', $program), [
        ...publicIntakeIdentityPayload($barangayId, [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'birthday' => '1985-05-05',
            'sex' => 'Female',
        ]),
        'intent' => 'submit',
        'consent' => true,
        'confirmed_beneficiary_id' => $beneficiary->id,
        'create_new' => false,
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('eligibility');
});

test('staff can still encode with an eligibility override', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();

    $individual = Individual::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'birthday' => '1992-02-02',
        'address_barangay_id' => $barangayId,
        'sex' => 'Female',
    ]);
    $beneficiary = Beneficiary::query()->where('beneficiable_id', $individual->id)->first();
    $mode = ModeOfRequest::query()->where('name', 'Walk In')->first();
    $inProgress = RequestSubStatus::query()->where('code', 'awaiting_review')->first()
        ?? RequestSubStatus::query()->where('name', 'Awaiting Review')->first();

    $open = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $open->id,
        'request_sub_status_id' => $inProgress->id,
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('user.programs.assistances.store', [
            'department' => $department->slug,
            'program' => $program->id,
        ]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'eligibility_override_reason' => 'Supervisor approved a second encoding.',
        ])
        ->assertRedirect();

    expect(Assistance::query()->count())->toBe(2);
});

test('guests cannot use staff encode routes', function () {
    ['department' => $department, 'program' => $program, 'item' => $item, 'barangayId' => $barangayId] = createPublicIntakeContext();

    $individual = Individual::factory()->create([
        'address_barangay_id' => $barangayId,
    ]);
    $beneficiary = Beneficiary::query()->where('beneficiable_id', $individual->id)->first();
    $mode = ModeOfRequest::query()->where('name', 'Walk In')->first();

    $this->post(route('user.programs.assistances.store', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'recorded_at' => now()->toDateString(),
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect();

    expect(Assistance::query()->count())->toBe(0);
});

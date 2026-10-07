<?php

use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\ModeOfRequest;
use App\Models\Organization;
use App\Models\Program;
use App\Services\User\BeneficiaryMorphService;
use App\Services\User\OrganizationBeneficiaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('beneficiary profile lists linked programs and assistances', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    $individual = Individual::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Cruz',
        'sex' => 'Male',
    ]);

    $beneficiary = app(BeneficiaryMorphService::class)->syncMorphRecord(
        $individual,
        $individual->cais_number,
        $individual->fullName(),
    );

    $program = Program::create([
        'name' => 'Rice Assistance',
        'descriptions' => 'Support program',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.beneficiaries.show', [
            'department' => $department->slug,
            'beneficiary' => $beneficiary->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/beneficiaries/show')
            ->where('beneficiary.name', 'Juan Cruz')
            ->where('beneficiary.assistances_count', 1)
            ->has('beneficiary.programs', 1)
            ->where('beneficiary.details.everify_status', 'skipped')
            ->where('beneficiary.details.everify_status_label', 'Manual entry'));
});

test('beneficiary profile exposes an assistance summary as a deferred prop', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    $individual = Individual::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Cruz',
        'sex' => 'Male',
    ]);

    $beneficiary = app(BeneficiaryMorphService::class)->syncMorphRecord(
        $individual,
        $individual->cais_number,
        $individual->fullName(),
    );

    $program = Program::create([
        'name' => 'Rice Assistance',
        'descriptions' => 'Support program',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->subDay()->toDateString(),
        'user_id' => $user->id,
    ]);

    Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'date_delivered' => now()->toDateString(),
        'was_delivered' => true,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(
            route('user.beneficiaries.show', [
                'department' => $department->slug,
                'beneficiary' => $beneficiary->id,
            ]),
            inertiaPartialHeaders('user/beneficiaries/show', 'assistance_summary'),
        )
        ->assertOk()
        ->assertJsonPath('props.assistance_summary.total', 2)
        ->assertJsonPath('props.assistance_summary.delivered', 1)
        ->assertJsonPath('props.assistance_summary.denied', 0)
        ->assertJsonPath('props.assistance_summary.in_progress', 1)
        ->assertJsonPath('props.assistance_summary.programs', 1)
        ->assertJsonPath('props.assistance_summary.last_requested_at', now()->toDateString());
});

test('organization beneficiary profile lists members', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    $barangayId = createAddressBarangay();

    $president = Individual::factory()->create([
        'first_name' => 'Ana',
        'middle_name' => null,
        'last_name' => 'Reyes',
        'sex' => 'Female',
    ]);

    $member = Individual::factory()->create([
        'first_name' => 'Pedro',
        'middle_name' => null,
        'last_name' => 'Garcia',
        'sex' => 'Male',
    ]);

    app(OrganizationBeneficiaryService::class)->create([
        'name' => 'Samahan ng Magsasaka',
        'beneficiary_id' => $president->id,
        'address_barangay_id' => $barangayId,
        'member_ids' => [$member->id],
        'total_member' => 2,
    ]);

    $beneficiary = Beneficiary::query()
        ->where('beneficiable_type', Organization::class)
        ->where('name', 'Samahan ng Magsasaka')
        ->firstOrFail();

    $presidentBeneficiary = app(BeneficiaryMorphService::class)->syncMorphRecord(
        $president,
        $president->cais_number,
        $president->fullName(),
    );

    app(BeneficiaryMorphService::class)->syncMorphRecord(
        $member,
        $member->cais_number,
        $member->fullName(),
    );

    $this->actingAs($user)
        ->get(route('user.beneficiaries.show', [
            'department' => $department->slug,
            'beneficiary' => $beneficiary->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/beneficiaries/show')
            ->where('beneficiary.type', 'organization')
            ->has('beneficiary.details.members', 2)
            ->where('beneficiary.details.members.0.name', 'Ana Reyes')
            ->where('beneficiary.details.members.0.is_president', true)
            ->where('beneficiary.details.members.0.beneficiary_id', $presidentBeneficiary->id)
            ->where('beneficiary.details.members.1.name', 'Pedro Garcia')
            ->where('beneficiary.details.members.1.is_president', false));
});

test('individual beneficiary profile lists organization memberships', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    $barangayId = createAddressBarangay();

    $president = Individual::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'sex' => 'Female',
    ]);

    $member = Individual::factory()->create([
        'first_name' => 'Pedro',
        'last_name' => 'Garcia',
        'sex' => 'Male',
    ]);

    $organization = app(OrganizationBeneficiaryService::class)->create([
        'name' => 'Samahan ng Magsasaka',
        'beneficiary_id' => $president->id,
        'address_barangay_id' => $barangayId,
        'member_ids' => [$member->id],
        'total_member' => 2,
    ]);

    $presidentBeneficiary = app(BeneficiaryMorphService::class)->syncMorphRecord(
        $president,
        $president->cais_number,
        $president->fullName(),
    );

    app(BeneficiaryMorphService::class)->syncMorphRecord(
        $member,
        $member->cais_number,
        $member->fullName(),
    );

    $organizationBeneficiary = app(BeneficiaryMorphService::class)->syncMorphRecord(
        $organization,
        $organization->cais_number,
        $organization->name,
    );

    $this->actingAs($user)
        ->get(route('user.beneficiaries.show', [
            'department' => $department->slug,
            'beneficiary' => $presidentBeneficiary->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/beneficiaries/show')
            ->where('beneficiary.type', 'individual')
            ->has('beneficiary.details.organizations', 1)
            ->where('beneficiary.details.organizations.0.name', 'Samahan ng Magsasaka')
            ->where('beneficiary.details.organizations.0.is_president', true)
            ->where('beneficiary.details.organizations.0.beneficiary_id', $organizationBeneficiary->id));

    $memberBeneficiary = Beneficiary::query()
        ->where('beneficiable_type', Individual::class)
        ->where('beneficiable_id', $member->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(route('user.beneficiaries.show', [
            'department' => $department->slug,
            'beneficiary' => $memberBeneficiary->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/beneficiaries/show')
            ->has('beneficiary.details.organizations', 1)
            ->where('beneficiary.details.organizations.0.name', 'Samahan ng Magsasaka')
            ->where('beneficiary.details.organizations.0.is_president', false));
});

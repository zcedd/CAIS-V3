<?php

use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot search beneficiaries', function () {
    $department = Department::create(['name' => 'Department A']);

    $response = $this->getJson(route('user.beneficiaries.search', [
        'department' => $department->slug,
        'q' => 'Juan',
    ]));

    $response->assertUnauthorized();
});

test('authenticated users can search beneficiaries in their department', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\Models\Individual',
        'beneficiable_id' => 1,
    ]);

    Beneficiary::create([
        'cais_number' => 'CAIS-002',
        'name' => 'Maria Santos',
        'beneficiable_type' => 'App\Models\Individual',
        'beneficiable_id' => 2,
    ]);

    $response = $this->actingAs($user)->getJson(route('user.beneficiaries.search', [
        'department' => $department->slug,
        'q' => 'Juan',
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.cais_number', 'CAIS-001')
        ->assertJsonPath('data.0.name', 'Juan Dela Cruz')
        ->assertJsonPath('data.0.individual_id', 1)
        ->assertJsonPath('data.0.label', 'CAIS-001 — Juan Dela Cruz')
        ->assertJsonPath('data.0.sex', null)
        ->assertJsonPath('data.0.age', null)
        ->assertJsonPath('data.0.barangay', null)
        ->assertJsonPath('data.0.city', null)
        ->assertJsonPath('data.0.sectors', []);
});

test('authenticated users can filter beneficiary search by type', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    Beneficiary::create([
        'cais_number' => 'CAIS-IND-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);

    Beneficiary::create([
        'cais_number' => 'CAIS-ORG-001',
        'name' => 'Samahan ng Magsasaka',
        'beneficiable_type' => 'App\\Models\\Organization',
        'beneficiable_id' => 2,
    ]);

    $this->actingAs($user)
        ->getJson(route('user.beneficiaries.search', [
            'department' => $department->slug,
            'beneficiary_type' => 'individual',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.cais_number', 'CAIS-IND-001');
});

test('beneficiary search returns profile details for people and leaves them empty for organizations', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    $individual = Individual::factory()->create([
        'cais_number' => 'CAIS-PROF-001',
        'first_name' => 'Rosa',
        'middle_name' => null,
        'last_name' => 'Reyes',
        'suffix' => null,
        'birthday' => now()->subYears(34)->subDay()->toDateString(),
        'sex' => 'Female',
        'pwd' => true,
        'is_solo_parent' => false,
        'is_4ps_beneficiary' => false,
        'indigenous' => false,
    ]);
    $individual->load('address.city');

    Organization::factory()->create([
        'cais_number' => 'CAIS-ORG-PROF',
        'name' => 'Rosa Community Hall',
        'address_barangay_id' => $individual->address_barangay_id,
    ]);

    $person = $this->actingAs($user)->getJson(route('user.beneficiaries.search', [
        'department' => $department->slug,
        'q' => 'CAIS-PROF-001',
    ]));

    $person->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Rosa Reyes')
        ->assertJsonPath('data.0.sex', 'Female')
        ->assertJsonPath('data.0.age', 34)
        ->assertJsonPath('data.0.barangay', $individual->address?->name)
        ->assertJsonPath('data.0.city', $individual->address?->city?->name)
        ->assertJsonPath('data.0.sectors', ['PWD'])
        ->assertJsonPath('data.0.organization_id', null);

    $this->actingAs($user)
        ->getJson(route('user.beneficiaries.search', [
            'department' => $department->slug,
            'q' => 'CAIS-ORG-PROF',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Rosa Community Hall')
        ->assertJsonPath('data.0.sex', null)
        ->assertJsonPath('data.0.age', null)
        ->assertJsonPath('data.0.sectors', [])
        ->assertJsonPath('data.0.individual_id', null)
        ->assertJsonPath('data.0.barangay', $individual->address?->name)
        ->assertJsonPath('data.0.city', $individual->address?->city?->name);
});

test('users cannot search beneficiaries for another department', function () {
    ['department' => $departmentA, 'user' => $user] = createBeneficiaryDepartmentUser();
    $departmentB = Department::create(['name' => 'Department B']);

    $this->actingAs($user)
        ->getJson(route('user.beneficiaries.search', [
            'department' => $departmentB->slug,
            'q' => 'Juan',
        ]))
        ->assertForbidden();
});

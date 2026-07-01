<?php

use App\Models\Beneficiary;
use App\Models\Department;
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
        ->assertJsonPath('data.0.label', 'CAIS-001 — Juan Dela Cruz');
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

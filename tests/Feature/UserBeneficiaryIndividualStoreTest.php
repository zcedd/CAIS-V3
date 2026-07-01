<?php

use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can create individual beneficiaries with morph row', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();

    $response = $this->actingAs($user)->post(route('user.beneficiaries.individuals.store', [
        'department' => $department->slug,
    ]), [
        'first_name' => 'Juan',
        'middle_name' => 'Dela',
        'last_name' => 'Cruz',
        'sex' => 'Male',
        'birthday' => '1990-01-01',
        'address_barangay_id' => $barangayId,
        'identifications' => [
            [
                'identification_id' => 1,
                'number' => 'NID-123456',
            ],
        ],
    ]);

    $individual = Individual::query()->where('first_name', 'Juan')->first();
    $beneficiary = Beneficiary::query()->where('beneficiable_id', $individual->id)->first();

    expect($individual)->not->toBeNull()
        ->and($beneficiary)->not->toBeNull()
        ->and($beneficiary->name)->toBe('Juan Dela Cruz')
        ->and($individual->cais_number)->toStartWith('IND-');

    $response->assertRedirect(route('user.beneficiaries.show', [
        'department' => $department->slug,
        'beneficiary' => $beneficiary->id,
    ]));
});

test('users cannot create beneficiaries for another department', function () {
    ['department' => $departmentA, 'user' => $user] = createBeneficiaryDepartmentUser();
    $departmentB = Department::create(['name' => 'Department B']);

    $this->actingAs($user)
        ->post(route('user.beneficiaries.individuals.store', [
            'department' => $departmentB->slug,
        ]), [
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'sex' => 'Male',
        ])
        ->assertForbidden();

    expect(Individual::query()->count())->toBe(0);
});

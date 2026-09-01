<?php

use App\Models\Department;
use App\Models\Individual;
use App\Models\IndividualIdentification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot search for duplicate beneficiaries', function () {
    $department = Department::create(['name' => 'Department A']);

    $this->getJson(route('user.beneficiaries.duplicates', [
        'department' => $department->slug,
        'beneficiary_type' => 'individual',
        'last_name' => 'Cruz',
    ]))->assertUnauthorized();
});

test('authenticated users can find duplicates by name and birthday', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    Individual::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $this->actingAs($user)
        ->getJson(route('user.beneficiaries.duplicates', [
            'department' => $department->slug,
            'beneficiary_type' => 'individual',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthday' => '1990-01-01',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.score', 'high');
});

test('creating an individual without acknowledging duplicates is rejected', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();

    Individual::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $this->actingAs($user)
        ->from(route('user.beneficiaries.create', ['department' => $department->slug]))
        ->post(route('user.beneficiaries.individuals.store', [
            'department' => $department->slug,
        ]), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'birthday' => '1990-01-01',
            'address_barangay_id' => $barangayId,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('duplicates')
        ->assertSessionHas('duplicate_candidates');

    expect(Individual::query()->where('first_name', 'Juan')->count())->toBe(1);
});

test('creating an individual succeeds when duplicates are acknowledged', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();

    Individual::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $this->actingAs($user)
        ->post(route('user.beneficiaries.individuals.store', [
            'department' => $department->slug,
        ]), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'birthday' => '1990-01-01',
            'address_barangay_id' => $barangayId,
            'duplicate_acknowledged' => true,
        ])
        ->assertRedirect();

    expect(Individual::query()->where('first_name', 'Juan')->count())->toBe(2);
});

test('duplicate search does not match unrelated names', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    Individual::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $this->actingAs($user)
        ->getJson(route('user.beneficiaries.duplicates', [
            'department' => $department->slug,
            'beneficiary_type' => 'individual',
            'first_name' => 'Pedro',
            'last_name' => 'Reyes',
            'birthday' => '1995-02-02',
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('duplicate search matches identification numbers', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();

    $individual = Individual::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);

    IndividualIdentification::query()->create([
        'beneficiary_id' => $individual->id,
        'identification_id' => 1,
        'number' => 'NID-999',
    ]);

    $this->actingAs($user)
        ->getJson(route('user.beneficiaries.duplicates', [
            'department' => $department->slug,
            'beneficiary_type' => 'individual',
            'last_name' => 'Other',
            'identifications' => [
                ['identification_id' => 1, 'number' => 'NID999'],
            ],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('duplicate search does not return unrelated names', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    Individual::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $this->actingAs($user)
        ->getJson(route('user.beneficiaries.duplicates', [
            'department' => $department->slug,
            'beneficiary_type' => 'individual',
            'first_name' => 'Pedro',
            'last_name' => 'Reyes',
            'birthday' => '1995-02-02',
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

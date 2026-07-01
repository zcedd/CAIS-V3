<?php

use App\Models\Individual;
use App\Services\User\BeneficiaryMorphService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('updating an individual refreshes the morph beneficiary name', function () {
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

    $this->actingAs($user)->put(route('user.beneficiaries.individuals.update', [
        'department' => $department->slug,
        'beneficiary' => $beneficiary->id,
    ]), [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'sex' => 'Female',
    ])->assertRedirect();

    $beneficiary->refresh();

    expect($beneficiary->name)->toBe('Maria Santos');
});

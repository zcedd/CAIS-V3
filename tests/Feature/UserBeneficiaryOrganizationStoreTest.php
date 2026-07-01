<?php

use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can create organization beneficiaries with president and members', function () {
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

    $response = $this->actingAs($user)->post(route('user.beneficiaries.organizations.store', [
        'department' => $department->slug,
    ]), [
        'name' => 'Samahan ng Magsasaka',
        'beneficiary_id' => $president->id,
        'addrs_brgy_id' => $barangayId,
        'member_ids' => [$member->id],
        'total_member' => 2,
    ]);

    $organization = Organization::query()->where('name', 'Samahan ng Magsasaka')->first();
    $beneficiary = Beneficiary::query()
        ->where('beneficiable_type', Organization::class)
        ->where('beneficiable_id', $organization->id)
        ->first();

    expect($organization)->not->toBeNull()
        ->and($beneficiary)->not->toBeNull()
        ->and($organization->beneficiary_id)->toBe($president->id)
        ->and($organization->beneficiary()->pluck('individual_organizations.beneficiary_id')->all())
        ->toContain($president->id, $member->id);

    $response->assertRedirect(route('user.beneficiaries.show', [
        'department' => $department->slug,
        'beneficiary' => $beneficiary->id,
    ]));
});

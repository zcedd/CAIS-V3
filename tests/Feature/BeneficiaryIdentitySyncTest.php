<?php

use App\Models\Beneficiary;
use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('saving an individual keeps the morph beneficiary identity in sync', function () {
    $individual = Individual::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Cruz',
        'suffix' => null,
    ]);

    $beneficiary = Beneficiary::query()
        ->where('beneficiable_type', Individual::class)
        ->where('beneficiable_id', $individual->id)
        ->first();

    expect($beneficiary)->not->toBeNull()
        ->and($beneficiary->name)->toBe('Juan Cruz')
        ->and($beneficiary->cais_number)->toBe($individual->cais_number);

    $individual->update([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
    ]);

    $beneficiary->refresh();

    expect($beneficiary->name)->toBe('Maria Santos');
});

test('saving an organization keeps the morph beneficiary identity in sync', function () {
    $president = Individual::factory()->create();
    $barangayId = createAddressBarangay();

    $organization = Organization::query()->create([
        'cais_number' => 'ORG-2026-9999',
        'name' => 'Original Org',
        'beneficiary_id' => $president->id,
        'address_barangay_id' => $barangayId,
        'mobile_number' => null,
        'total_member' => 1,
    ]);

    $beneficiary = Beneficiary::query()
        ->where('beneficiable_type', Organization::class)
        ->where('beneficiable_id', $organization->id)
        ->first();

    expect($beneficiary)->not->toBeNull()
        ->and($beneficiary->name)->toBe('Original Org')
        ->and($beneficiary->cais_number)->toBe('ORG-2026-9999');

    $organization->update(['name' => 'Renamed Org']);
    $beneficiary->refresh();

    expect($beneficiary->name)->toBe('Renamed Org');
});

test('reconcile command repairs drifted beneficiary identity rows', function () {
    $individual = Individual::factory()->create([
        'first_name' => 'Ana',
        'middle_name' => null,
        'last_name' => 'Reyes',
        'suffix' => null,
    ]);

    $beneficiary = $individual->beneficiaryRecord;
    expect($beneficiary)->not->toBeNull();

    DB::table('beneficiaries')->where('id', $beneficiary->id)->update([
        'name' => 'Stale Name',
        'cais_number' => 'STALE-0001',
    ]);

    Artisan::call('beneficiaries:reconcile-identity');

    $beneficiary->refresh();

    expect($beneficiary->name)->toBe('Ana Reyes')
        ->and($beneficiary->cais_number)->toBe($individual->cais_number);
});

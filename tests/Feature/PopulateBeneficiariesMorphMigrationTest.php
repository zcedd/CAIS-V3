<?php

use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('populate migration creates morph beneficiaries and links assistances', function () {
    Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_05_19_064642_populate_beneficiaries_morph_and_link_assistances.php',
    ]);

    $cityId = DB::table('address_cities')->insertGetId([
        'name' => 'Test City',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $barangayId = DB::table('address_barangays')->insertGetId([
        'name' => 'Test Barangay',
        'address_city_id' => $cityId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $individualId = DB::table('individuals')->insertGetId([
        'cais_number' => 'IND-TEST-001',
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Cruz',
        'suffix' => null,
        'birthday' => null,
        'sex' => 'Male',
        'other_address' => null,
        'mobile_number' => null,
        'indigenous' => false,
        'pwd' => false,
        'is_4ps_beneficiary' => false,
        'is_solo_parent' => false,
        'address_barangay_id' => $barangayId,
        'civil_status_id' => null,
        'ethnicity' => null,
        'spouse' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    $organizationId = DB::table('organizations')->insertGetId([
        'cais_number' => 'ORG-TEST-001',
        'name' => 'Test Organization',
        'mobile_number' => null,
        'beneficiary_id' => $individualId,
        'addrs_brgy_id' => $barangayId,
        'total_member' => 10,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    $departmentId = DB::table('departments')->insertGetId([
        'name' => 'Test Department',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'department_id' => $departmentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $programId = DB::table('programs')->insertGetId([
        'name' => 'Test Program',
        'descriptions' => null,
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentId,
        'is_closed' => false,
        'is_organization' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $individualAssistanceId = DB::table('assistances')->insertGetId([
        'project_id' => $programId,
        'beneficiary_id' => null,
        'individual_id' => $individualId,
        'organization_id' => null,
        'mode_of_request_id' => null,
        'date_requested' => now()->toDateString(),
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => null,
        'user_id' => $userId,
        'remark' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    $organizationAssistanceId = DB::table('assistances')->insertGetId([
        'project_id' => $programId,
        'beneficiary_id' => null,
        'individual_id' => null,
        'organization_id' => $organizationId,
        'mode_of_request_id' => null,
        'date_requested' => now()->toDateString(),
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => null,
        'user_id' => $userId,
        'remark' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    expect(DB::table('beneficiaries')->count())->toBe(0);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_05_19_064642_populate_beneficiaries_morph_and_link_assistances.php',
    ]);

    $individualBeneficiaryId = DB::table('beneficiaries')
        ->where('beneficiable_type', Individual::class)
        ->where('beneficiable_id', $individualId)
        ->value('id');

    $organizationBeneficiaryId = DB::table('beneficiaries')
        ->where('beneficiable_type', Organization::class)
        ->where('beneficiable_id', $organizationId)
        ->value('id');

    expect($individualBeneficiaryId)->not->toBeNull();
    expect($organizationBeneficiaryId)->not->toBeNull();

    expect(DB::table('beneficiaries')->where('id', $individualBeneficiaryId)->value('name'))
        ->toBe('Juan Cruz');

    expect(DB::table('beneficiaries')->where('id', $organizationBeneficiaryId)->value('name'))
        ->toBe('Test Organization');

    expect(DB::table('assistances')->where('id', $individualAssistanceId)->value('beneficiary_id'))
        ->toBe($individualBeneficiaryId);

    expect(DB::table('assistances')->where('id', $organizationAssistanceId)->value('beneficiary_id'))
        ->toBe($organizationBeneficiaryId);
});

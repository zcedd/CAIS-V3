<?php

use App\Models\Beneficiary;
use App\Services\User\BeneficiaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view the beneficiaries index page', function () {
    $response = $this->get(route('user.beneficiaries.index', ['department' => 'any-department']));

    $response->assertRedirect(route('login'));
});

test('users can filter beneficiaries by type', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    $individual = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\Models\Individual',
        'beneficiable_id' => 1,
    ]);

    Beneficiary::create([
        'cais_number' => 'CAIS-002',
        'name' => 'Acme Foundation',
        'beneficiable_type' => 'App\Models\Organization',
        'beneficiable_id' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('user.beneficiaries.index', [
            'department' => $department->slug,
            'type' => ['individual'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/beneficiaries/index')
            ->has('beneficiaries.data', 1)
            ->where('beneficiaries.data.0.id', $individual->id)
            ->where('beneficiaries.data.0.type', 'individual')
            ->where('beneficiaries.data.0.assistances_count', 0)
            ->where('beneficiaries.data.0.last_assisted_at', null)
            ->where('beneficiaries.data.0.address', null)
            ->where('beneficiaries.data.0.contact', null)
            ->has('beneficiaries.data.0.registered_at')
            ->where('type', ['individual']));
});

test('beneficiaries index exposes registry stats as a deferred prop', function () {
    createBeneficiaryDepartmentUser();

    Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\Models\Individual',
        'beneficiable_id' => 1,
    ]);

    Beneficiary::create([
        'cais_number' => 'CAIS-002',
        'name' => 'Acme Foundation',
        'beneficiable_type' => 'App\Models\Organization',
        'beneficiable_id' => 1,
    ]);

    $stats = app(BeneficiaryService::class)->registryStats();

    expect($stats)->toMatchArray([
        'total' => 2,
        'individuals' => 1,
        'organizations' => 1,
        'assisted' => 0,
        'new_this_month' => 2,
    ]);
});

test('beneficiary form options are cached after the first load', function () {
    createBeneficiaryDepartmentUser();

    $service = app(BeneficiaryService::class);

    $first = $service->formOptions();
    $second = $service->formOptions();

    expect($second)->toEqual($first)
        ->and($first)->toHaveKeys([
            'civil_statuses',
            'identifications',
            'address_provinces',
            'default_province_id',
            'address_cities',
            'address_barangays',
        ]);

    $encoded = json_decode(json_encode($second), true);

    expect($encoded['civil_statuses'])->toBeArray()
        ->and(array_is_list($encoded['civil_statuses']))->toBeTrue()
        ->and($encoded['identifications'])->toBeArray()
        ->and(array_is_list($encoded['identifications']))->toBeTrue()
        ->and($encoded['address_provinces'])->toBeArray()
        ->and(array_is_list($encoded['address_provinces']))->toBeTrue()
        ->and($encoded['address_cities'])->toBeArray()
        ->and(array_is_list($encoded['address_cities']))->toBeTrue();
});

test('create beneficiary page receives civil status options as a list', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    seedCivilStatusAndIdentification();

    $this->actingAs($user)
        ->get(route('user.beneficiaries.create', [
            'department' => $department->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/beneficiaries/create')
            ->has('form_options.civil_statuses.0.id')
            ->where('form_options.civil_statuses.0.name', 'Single'));
});

test('users can load additional beneficiaries via pagination', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    foreach (range(1, 16) as $index) {
        Beneficiary::create([
            'cais_number' => sprintf('CAIS-%03d', $index),
            'name' => "Beneficiary {$index}",
            'beneficiable_type' => 'App\Models\Individual',
            'beneficiable_id' => $index,
        ]);
    }

    $this->actingAs($user)
        ->get(route('user.beneficiaries.index', [
            'department' => $department->slug,
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('beneficiaries.data', 15)
            ->where('beneficiaries.current_page', 1)
            ->where('beneficiaries.per_page', 15)
            ->where('beneficiaries.total', 16)
            ->where('per_page', 15));

    $this->actingAs($user)
        ->get(route('user.beneficiaries.index', [
            'department' => $department->slug,
            'per_page' => 15,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('beneficiaries.data', 1)
            ->where('beneficiaries.current_page', 2)
            ->where('beneficiaries.data.0.name', 'Beneficiary 9'));
});

test('beneficiaries index uses the default page size when none is given', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    $this->actingAs($user)
        ->get(route('user.beneficiaries.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('beneficiaries.per_page', 25)
            ->where('per_page', 25));
});

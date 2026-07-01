<?php

use App\Models\Beneficiary;
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
            ->where('type', ['individual']));
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
        ->get(route('user.beneficiaries.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('beneficiaries.data', 15)
            ->where('beneficiaries.current_page', 1)
            ->where('beneficiaries.total', 16));

    $this->actingAs($user)
        ->get(route('user.beneficiaries.index', [
            'department' => $department->slug,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('beneficiaries.data', 1)
            ->where('beneficiaries.current_page', 2)
            ->where('beneficiaries.data.0.name', 'Beneficiary 16'));
});

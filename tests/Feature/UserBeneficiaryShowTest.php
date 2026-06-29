<?php

use App\Models\Assistance;
use App\Models\Individual;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Services\User\BeneficiaryMorphService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('beneficiary profile lists linked programs and assistances', function () {
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

    $program = Program::create([
        'name' => 'Rice Assistance',
        'descriptions' => 'Support program',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.beneficiaries.show', [
            'department' => $department->slug,
            'beneficiary' => $beneficiary->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/beneficiaries/show')
            ->where('beneficiary.name', 'Juan Cruz')
            ->where('beneficiary.assistances_count', 1)
            ->has('beneficiary.programs', 1));
});

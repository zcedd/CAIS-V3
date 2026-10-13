<?php

use App\Models\Assistance;
use App\Models\Department;
use App\Models\Individual;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use App\Services\User\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard demographics count each age group by sex', function () {
    $department = Department::create(['name' => 'Welfare']);
    $otherDepartment = Department::create(['name' => 'Other']);
    $program = Program::create([
        'name' => 'Demo Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $otherProgram = Program::create([
        'name' => 'Other Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $otherDepartment->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $user = User::factory()->create(['department_id' => $department->id]);

    createDemographicRequest($program, $user, [
        'sex' => 'Male',
        'birthday' => now()->subYears(25)->toDateString(),
    ]);
    createDemographicRequest($program, $user, [
        'sex' => 'Female',
        'birthday' => now()->subYears(25)->toDateString(),
    ]);
    createDemographicRequest($program, $user, [
        'sex' => 'Male',
        'birthday' => now()->subYears(70)->toDateString(),
    ]);
    createDemographicRequest($program, $user, [
        'sex' => 'Female',
        'birthday' => null,
    ]);
    createDemographicRequest($program, $user, [
        'sex' => '',
        'birthday' => now()->subYears(10)->toDateString(),
    ]);
    createDemographicRequest($otherProgram, $user, [
        'sex' => 'Male',
        'birthday' => now()->subYears(70)->toDateString(),
    ]);

    $demographics = app(DashboardService::class)->demographics($department, []);
    $ageBySex = collect($demographics['age_by_sex'])->keyBy('label');

    expect($ageBySex->get('18-29'))->toMatchArray([
        'label' => '18-29',
        'male' => 1,
        'female' => 1,
        'other' => 0,
    ])
        ->and($ageBySex->get('60+'))->toMatchArray([
            'label' => '60+',
            'male' => 1,
            'female' => 0,
            'other' => 0,
        ])
        ->and($ageBySex->get('Unspecified'))->toMatchArray([
            'label' => 'Unspecified',
            'male' => 0,
            'female' => 1,
            'other' => 0,
        ])
        ->and($ageBySex->get('Under 18'))->toMatchArray([
            'label' => 'Under 18',
            'male' => 0,
            'female' => 0,
            'other' => 1,
        ])
        ->and(collect($demographics['age'])->firstWhere('label', '18-29'))->toMatchArray([
            'label' => '18-29',
            'count' => 2,
        ]);
});

/**
 * @param  array{sex: string, birthday: string|null}  $individualAttributes
 */
function createDemographicRequest(Program $program, User $user, array $individualAttributes): void
{
    $individual = Individual::factory()->create($individualAttributes);
    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
}

<?php

use App\Enums\ProgramKind;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('standalone family ids include only itself', function () {
    $program = Program::factory()->standalone()->create();

    expect($program->familyIds())->toBe([$program->id])
        ->and($program->eligibilityProgram()->is($program))->toBeTrue()
        ->and($program->isEncodable())->toBeTrue();
});

test('scheme family ids include sibling batches and not the parent', function () {
    $department = Department::create(['name' => 'Department A']);
    $scheme = Program::factory()->scheme()->forDepartment($department)->create();
    $first = Program::factory()->batch($scheme)->create([
        'batch_number' => 1,
        'batch_name' => 'Batch 1',
        'name' => Program::composeBatchDisplayName($scheme->name, 'Batch 1'),
    ]);
    $second = Program::factory()->batch($scheme)->create([
        'batch_number' => 2,
        'batch_name' => 'Batch 2',
        'name' => Program::composeBatchDisplayName($scheme->name, 'Batch 2'),
    ]);

    expect($scheme->isEncodable())->toBeFalse()
        ->and($scheme->familyIds())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($first->familyIds())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($second->eligibilityProgram()->is($scheme))->toBeTrue();
});

test('a scheme with no batches is not encodable', function () {
    $scheme = Program::factory()->scheme()->create();

    expect($scheme->kind)->toBe(ProgramKind::Scheme)
        ->and($scheme->isEncodable())->toBeFalse()
        ->and($scheme->familyIds())->toBe([]);
});

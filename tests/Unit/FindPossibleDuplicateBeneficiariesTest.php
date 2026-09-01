<?php

use App\Actions\User\FindPossibleDuplicateBeneficiaries;
use App\Models\Individual;
use App\Models\IndividualIdentification;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createDuplicateIndividual(array $attributes = []): Individual
{
    return Individual::factory()->create($attributes)->refresh();
}

test('it matches individuals by identification number', function () {
    seedCivilStatusAndIdentification();

    $existing = createDuplicateIndividual([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'birthday' => '1988-05-01',
    ]);

    IndividualIdentification::query()->create([
        'beneficiary_id' => $existing->id,
        'identification_id' => 1,
        'number' => 'NID-123456',
    ]);

    $matches = app(FindPossibleDuplicateBeneficiaries::class)([
        'type' => 'individual',
        'first_name' => 'Other',
        'last_name' => 'Person',
        'identifications' => [
            ['identification_id' => 1, 'number' => 'NID 123-456'],
        ],
    ]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['score'])->toBe('high')
        ->and($matches[0]['match_reasons'])->toContain('Same ID number');
});

test('it matches individuals by name and birthday', function () {
    $existing = createDuplicateIndividual([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $matches = app(FindPossibleDuplicateBeneficiaries::class)([
        'type' => 'individual',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['id'])->toBe($existing->beneficiaryRecord->id)
        ->and($matches[0]['score'])->toBe('high');
});

test('it does not match unrelated names', function () {
    createDuplicateIndividual([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $matches = app(FindPossibleDuplicateBeneficiaries::class)([
        'type' => 'individual',
        'first_name' => 'Pedro',
        'last_name' => 'Reyes',
        'birthday' => '1995-02-02',
    ]);

    expect($matches)->toBeEmpty();
});

test('it excludes the beneficiary being edited', function () {
    $existing = createDuplicateIndividual([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
    ]);

    $matches = app(FindPossibleDuplicateBeneficiaries::class)([
        'type' => 'individual',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'birthday' => '1990-01-01',
        'exclude_beneficiary_id' => $existing->beneficiaryRecord->id,
    ]);

    expect($matches)->toBeEmpty();
});

test('it matches organizations by name and barangay', function () {
    $barangayId = createAddressBarangay();
    $president = Individual::factory()->create();

    $organization = Organization::factory()->create([
        'name' => 'Samahan ng Magsasaka',
        'address_barangay_id' => $barangayId,
        'beneficiary_id' => $president->id,
    ]);

    $matches = app(FindPossibleDuplicateBeneficiaries::class)([
        'type' => 'organization',
        'name' => 'Samahan ng Magsasaka',
        'address_barangay_id' => $barangayId,
    ]);

    expect($matches)->not->toBeEmpty()
        ->and($matches[0]['score'])->toBe('high');
});

test('it scores individual matches by name birthday and barangay', function (array $existing, array $search, string $score) {
    $barangayId = createAddressBarangay();

    createDuplicateIndividual([
        ...$existing,
        'address_barangay_id' => $barangayId,
    ]);

    $matches = app(FindPossibleDuplicateBeneficiaries::class)([
        'type' => 'individual',
        ...$search,
        'address_barangay_id' => $barangayId,
    ]);

    expect($matches)->not->toBeEmpty()
        ->and($matches[0]['score'])->toBe($score);
})->with([
    'high name and birthday' => [
        ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'birthday' => '1990-01-01'],
        ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'birthday' => '1990-01-01'],
        'high',
    ],
    'medium last name birthday and barangay' => [
        ['first_name' => 'Maria', 'last_name' => 'Santos', 'birthday' => '1988-05-01'],
        ['first_name' => 'Ana', 'last_name' => 'Santos', 'birthday' => '1988-05-01'],
        'medium',
    ],
    'low name and barangay without birthday' => [
        ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'birthday' => '1990-01-01'],
        ['first_name' => 'Juan', 'last_name' => 'Dela Cruz'],
        'low',
    ],
]);

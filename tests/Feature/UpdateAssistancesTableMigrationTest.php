<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('links assistances individual_id to individuals and beneficiary_id to beneficiaries', function () {
    expect(Schema::hasColumn('assistances', 'individual_id'))->toBeTrue();
    expect(Schema::hasColumn('assistances', 'beneficiary_id'))->toBeTrue();

    $foreignKeys = collect(DB::select(
        'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND REFERENCED_TABLE_NAME IS NOT NULL',
        ['assistances']
    ))->mapWithKeys(fn ($row) => [$row->COLUMN_NAME => $row->REFERENCED_TABLE_NAME]);

    expect($foreignKeys->get('individual_id'))->toBe('individuals');
    expect($foreignKeys->get('beneficiary_id'))->toBe('beneficiaries');

    $constraintNames = collect(DB::select(
        'SELECT CONSTRAINT_NAME
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND CONSTRAINT_TYPE = ?',
        ['assistances', 'FOREIGN KEY']
    ))->pluck('CONSTRAINT_NAME');

    expect($constraintNames)->toContain('assistances_morph_beneficiary_id_foreign');
});

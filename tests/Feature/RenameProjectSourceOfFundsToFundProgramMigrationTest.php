<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('renames project_source_of_fund to fund_program with fund_id and program_id foreign keys', function () {
    expect(Schema::hasTable('fund_program'))->toBeTrue();
    expect(Schema::hasTable('project_source_of_fund'))->toBeFalse();

    expect(Schema::hasColumns('fund_program', ['fund_id', 'program_id']))->toBeTrue();

    $foreignKeys = collect(DB::select(
        'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND REFERENCED_TABLE_NAME IS NOT NULL',
        ['fund_program']
    ))->mapWithKeys(fn ($row) => [$row->COLUMN_NAME => $row->REFERENCED_TABLE_NAME]);

    expect($foreignKeys->get('fund_id'))->toBe('funds');
    expect($foreignKeys->get('program_id'))->toBe('programs');
});

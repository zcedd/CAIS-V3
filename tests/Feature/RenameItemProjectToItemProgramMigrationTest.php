<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('renames item_project to item_program with program_id foreign key', function () {
    expect(Schema::hasTable('item_program'))->toBeTrue();
    expect(Schema::hasTable('item_project'))->toBeFalse();
    expect(Schema::hasColumns('item_program', ['program_id', 'item_id']))->toBeTrue();

    $foreignKeys = collect(DB::select(
        'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND REFERENCED_TABLE_NAME IS NOT NULL',
        ['item_program']
    ))->mapWithKeys(fn ($row) => [$row->COLUMN_NAME => $row->REFERENCED_TABLE_NAME]);

    expect($foreignKeys->get('program_id'))->toBe('programs');
    expect($foreignKeys->get('item_id'))->toBe('items');
});

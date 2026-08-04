<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('links assistances beneficiary_id to beneficiaries and drops the legacy columns', function () {
    expect(Schema::hasColumn('assistances', 'beneficiary_id'))->toBeTrue();
    expect(Schema::hasColumn('assistances', 'individual_id'))->toBeFalse();
    expect(Schema::hasColumn('assistances', 'organization_id'))->toBeFalse();

    if (DB::getDriverName() !== 'mysql') {
        return;
    }

    $foreignKeys = collect(Schema::getForeignKeys('assistances'));

    expect($foreignKeys->contains(
        fn (array $foreignKey): bool => in_array('beneficiary_id', $foreignKey['columns'], true)
            && $foreignKey['foreign_table'] === 'beneficiaries',
    ))->toBeTrue();

    expect($foreignKeys->pluck('name'))->toContain('assistances_morph_beneficiary_id_foreign');
});

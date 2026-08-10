<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>|null
 */
function remarkColumn(string $table): ?array
{
    return collect(Schema::getColumns($table))
        ->first(static fn (array $column): bool => ($column['name'] ?? null) === 'remark');
}

test('assistance remark columns use text storage', function () {
    $assistanceRemark = remarkColumn('assistances');
    $statusRemark = remarkColumn('assistance_request_sub_status');

    expect($assistanceRemark)->not->toBeNull()
        ->and($statusRemark)->not->toBeNull();

    $assistanceType = strtolower((string) ($assistanceRemark['type_name'] ?? $assistanceRemark['type'] ?? ''));
    $statusType = strtolower((string) ($statusRemark['type_name'] ?? $statusRemark['type'] ?? ''));

    expect($assistanceType)->not->toContain('long')
        ->and($statusType)->not->toContain('long');
});

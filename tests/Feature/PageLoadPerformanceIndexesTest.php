<?php

use App\Models\Department;
use App\Models\User;
use App\Services\User\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $columns
 */
function tableHasIndexCovering(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))->contains(
        function (array $definition) use ($columns): bool {
            $indexColumns = $definition['columns'] ?? [];

            return array_slice($indexColumns, 0, count($columns)) === $columns;
        },
    );
}

test('page load performance indexes cover hot query columns', function () {
    expect(tableHasIndexCovering('assistances', ['program_id', 'date_requested']))->toBeTrue()
        ->and(tableHasIndexCovering('assistances', ['beneficiary_id']))->toBeTrue()
        ->and(tableHasIndexCovering('assistance_item', ['assistance_id', 'is_received', 'deleted_at']))->toBeTrue()
        ->and(tableHasIndexCovering('notifications', ['notifiable_type', 'notifiable_id', 'read_at']))->toBeTrue()
        ->and(tableHasIndexCovering('programs', ['department_id', 'is_closed']))->toBeTrue()
        ->and(tableHasIndexCovering('beneficiaries', ['name']))->toBeTrue();

    if (Schema::hasTable('address_barangays')) {
        expect(tableHasIndexCovering('address_barangays', ['address_city_id']))->toBeTrue();
    }
});

test('unread notification count uses null read_at filter', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create(['department_id' => $department->id]);

    DB::table('notifications')->insert([
        [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Unread'], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Read'], JSON_THROW_ON_ERROR),
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(app(NotificationService::class)->unreadCountForUser($user))->toBe(1);
});

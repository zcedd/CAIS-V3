<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('role permission seeder migrates legacy admin users to super admin', function (string $legacyRoleName) {
    $legacyRole = Role::create(['name' => $legacyRoleName, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($legacyRole);

    $this->seed(RolePermissionSeeder::class);

    $user->refresh();

    expect($user->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
        ->and($user->hasRole($legacyRoleName))->toBeFalse()
        ->and(Role::query()->where('name', $legacyRoleName)->where('guard_name', 'web')->exists())->toBeFalse();
})->with(['admin', 'Admin']);

test('role permission seeder moves head users to department head', function () {
    $head = Role::create(['name' => RoleName::Head->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($head);

    $this->seed(RolePermissionSeeder::class);

    $user->refresh();

    expect($user->hasRole(RoleName::DepartmentHead->value))->toBeTrue()
        ->and($user->hasRole(RoleName::Head->value))->toBeFalse();
});

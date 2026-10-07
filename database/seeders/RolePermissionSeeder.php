<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const LEGACY_PERMISSIONS = [
        'Update Assistance',
        'Delete Assistance',
        'Download Assistance',
        'Create Assistance',
        'Update Beneficiary',
        'Create Project',
        'Update Project',
        'Update Organization',
        'Create Beneficiary',
        'Create Organization',
        'Supervise Department',
    ];

    /**
     * @var list<string>
     */
    private const LEGACY_ADMIN_ROLES = [
        'admin',
        'Admin',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $superAdmin = Role::findOrCreate(RoleName::SuperAdmin->value, 'web');
        $superAdmin->syncPermissions(
            array_map(
                static fn (PermissionName $permission): string => $permission->value,
                PermissionName::forRole(RoleName::SuperAdmin),
            ),
        );

        foreach (RoleName::resourceRoles() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions(
                array_map(
                    static fn (PermissionName $permission): string => $permission->value,
                    PermissionName::forRole($roleName),
                ),
            );
        }

        $supervisor = Role::findOrCreate(RoleName::Supervisor->value, 'web');
        $supervisor->syncPermissions([PermissionName::DepartmentSupervise->value]);

        $head = Role::findOrCreate(RoleName::Head->value, 'web');
        $head->syncPermissions(
            array_map(
                static fn (PermissionName $permission): string => $permission->value,
                PermissionName::forRole(RoleName::Head),
            ),
        );

        foreach ([RoleName::Governor, RoleName::DepartmentHead, RoleName::ReleasingOfficer] as $office) {
            $role = Role::findOrCreate($office->value, 'web');
            $role->syncPermissions(
                array_map(
                    static fn (PermissionName $permission): string => $permission->value,
                    PermissionName::forRole($office),
                ),
            );
        }

        $this->migrateHeadUsers();
        $this->migrateLegacyAdminUsers($superAdmin);
        $this->deleteLegacyRoles();
        $this->deleteLegacyPermissions();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function migrateHeadUsers(): void
    {
        $head = Role::query()
            ->where('name', RoleName::Head->value)
            ->where('guard_name', 'web')
            ->first();

        $departmentHead = Role::query()
            ->where('name', RoleName::DepartmentHead->value)
            ->where('guard_name', 'web')
            ->first();

        if ($head === null || $departmentHead === null) {
            return;
        }

        foreach ($head->users()->get() as $user) {
            if (! $user->hasRole($departmentHead)) {
                $user->assignRole($departmentHead);
            }

            $user->removeRole($head);
        }
    }

    private function migrateLegacyAdminUsers(Role $superAdmin): void
    {
        foreach (self::LEGACY_ADMIN_ROLES as $legacyName) {
            $legacy = Role::query()
                ->where('name', $legacyName)
                ->where('guard_name', 'web')
                ->first();

            if ($legacy === null || $legacy->id === $superAdmin->id) {
                continue;
            }

            foreach ($legacy->users()->with('roles')->get() as $user) {
                $user->assignRole($superAdmin);
                $user->removeRole($legacy);
            }

            $legacy->delete();
        }
    }

    private function deleteLegacyRoles(): void
    {
        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'user')
            ->delete();
    }

    private function deleteLegacyPermissions(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::LEGACY_PERMISSIONS)
            ->delete();
    }
}

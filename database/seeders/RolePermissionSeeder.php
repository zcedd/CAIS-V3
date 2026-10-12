<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\DistributionApprovalStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
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
        $head->syncPermissions(PermissionName::values());

        foreach ([RoleName::Executive, RoleName::DepartmentHead, RoleName::ReleasingOfficer] as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions(
                array_map(
                    static fn (PermissionName $permission): string => $permission->value,
                    PermissionName::forRole($roleName),
                ),
            );
        }

        $this->migrateLegacyRole(
            'governor',
            Role::findByName(RoleName::Executive->value, 'web'),
        );
        $this->renameDistributionStageRole('governor', RoleName::Executive->value);
        $this->migrateLegacyAdminUsers($superAdmin);
        $this->deleteLegacyRoles();
        $this->deleteLegacyPermissions();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function migrateLegacyAdminUsers(Role $superAdmin): void
    {
        foreach (self::LEGACY_ADMIN_ROLES as $legacyName) {
            $this->migrateLegacyRole($legacyName, $superAdmin);
        }
    }

    private function migrateLegacyRole(string $legacyName, Role $replacement): void
    {
        $legacy = Role::query()
            ->where('name', $legacyName)
            ->where('guard_name', 'web')
            ->first();

        if ($legacy === null || $legacy->id === $replacement->id) {
            return;
        }

        foreach ($legacy->users()->with('roles')->get() as $user) {
            $user->assignRole($replacement);
            $user->removeRole($legacy);
        }

        $legacy->delete();
    }

    private function renameDistributionStageRole(string $from, string $to): void
    {
        if (! Schema::hasTable('distribution_approval_stages')) {
            return;
        }

        DistributionApprovalStage::query()
            ->where('role_name', $from)
            ->update(['role_name' => $to]);
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
            ->where(function ($query): void {
                $query->whereIn('name', self::LEGACY_PERMISSIONS)
                    ->orWhere('name', 'like', 'dispersal.%');
            })
            ->delete();
    }
}

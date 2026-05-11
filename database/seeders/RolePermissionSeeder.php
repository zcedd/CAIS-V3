<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['name' => 'Update Assistance']);
        Permission::create(['name' => 'Delete Assistance']);
        Permission::create(['name' => 'Download Assistance']);
        Permission::create(['name' => 'Create Assistance']);
        Permission::create(['name' => 'Update Beneficiary']);
        Permission::create(['name' => 'Create Project']);
        Permission::create(['name' => 'Update Project']);
        Permission::create(['name' => 'Update Organization']);
        Permission::create(['name' => 'Create Beneficiary']);
        Permission::create(['name' => 'Create Organization']);
        Permission::create(['name' => 'Supervise Department']);

        // create roles and assign existing permissions
        $role1 = Role::create(['name' => 'admin']);
        
        $role2 = Role::create(['name' => 'head']);
        $role2->givePermissionTo('Update Assistance');
        $role2->givePermissionTo('Delete Assistance');
        $role2->givePermissionTo('Download Assistance');
        $role2->givePermissionTo('Create Assistance');
        $role2->givePermissionTo('Create Project');
        $role2->givePermissionTo('Update Project');
        $role2->givePermissionTo('Update Organization');
        $role2->givePermissionTo('Create Beneficiary');
        $role2->givePermissionTo('Create Organization');
        
        $role3 = Role::create(['name' => 'user']);

        $role4 = Role::create(['name' => 'supervisor']);
        $role4->givePermissionTo('Supervise Department');
    }
}

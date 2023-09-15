<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::updateOrCreate(['name' => 'Administrator']);
        $this->managerRole();
        $this->userRole();
    }

    private function managerRole()
    {
        $role = Role::updateOrCreate(['name' => 'Manager']);

        $role->givePermissionTo([
            'user.create',
            'user.update',
            'user.delete',
            'user.update_self',
            'user.assign_role',
        ]);
    }

    private function userRole()
    {
        $role = Role::updateOrCreate(['name' => 'User']);

        $role->givePermissionTo([
            'user.update_self',
        ]);
    }
}

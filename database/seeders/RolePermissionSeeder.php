<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guardName = 'web';

        $permissionNames = [
            'users.view',
            'users.create',
            'users.update',
            'users.deactivate',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'project-access.manage',
        ];

        foreach ($permissionNames as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guardName,
            ]);
        }

        $superAdminRole = Role::query()->firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => $guardName,
        ]);

        $superAdminRole->syncPermissions($permissionNames);

        $adminUser = User::query()
            ->where('email', 'admin@property.test')
            ->firstOrFail();

        $adminUser->syncRoles([$superAdminRole]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

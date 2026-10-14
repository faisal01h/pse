<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $keys = array_keys(config('authorization.permissions', []));

        foreach ($keys as $key) {
            Permission::findOrCreate($key, 'web');
        }

        $admin = Role::findOrCreate(config('authorization.admin_role', 'admin'), 'web');
        $admin->syncPermissions($keys);

        User::query()->each(function (User $user) use ($admin) {
            if (! $user->hasRole($admin)) {
                $user->assignRole($admin);
            }
        });
    }
}

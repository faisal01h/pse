<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AccessGuard
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $permissionNames
     */
    public static function assertNotStrandingLastManager(
        User $target,
        bool $willBeActive,
        array $roleNames,
        array $permissionNames,
    ): void {
        if (! $target->isLastActiveAccessManager()) {
            return;
        }

        $wouldKeepManage = self::wouldHaveManageAccess($willBeActive, $roleNames, $permissionNames);

        if ($wouldKeepManage) {
            return;
        }

        throw ValidationException::withMessages([
            'roles' => 'Tidak dapat menonaktifkan atau mencabut akses pengelola dari satu-satunya pengelola aktif yang tersisa.',
        ]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public static function assertAdminRoleKeepsManagePermissions(Role $role, array $permissionNames): void
    {
        $adminName = config('authorization.admin_role', 'admin');

        if ($role->name !== $adminName) {
            return;
        }

        foreach (['roles.manage', 'users.manage'] as $required) {
            if (! in_array($required, $permissionNames, true)) {
                throw ValidationException::withMessages([
                    'permissions' => 'Peran admin harus tetap memiliki izin mengelola peran dan pengguna.',
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $permissionNames
     */
    private static function wouldHaveManageAccess(bool $active, array $roleNames, array $permissionNames): bool
    {
        if (! $active) {
            return false;
        }

        if (array_intersect($permissionNames, ['roles.manage', 'users.manage']) !== []) {
            return true;
        }

        if ($roleNames === []) {
            return false;
        }

        return Role::query()
            ->whereIn('name', $roleNames)
            ->whereHas('permissions', function ($query) {
                $query->whereIn('name', ['roles.manage', 'users.manage']);
            })
            ->exists();
    }
}

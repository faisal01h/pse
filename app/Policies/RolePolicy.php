<?php

namespace App\Policies;

use App\Authorization\Abac;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return Abac::allows($user, $user->hasPermissionTo('roles.view'));
    }

    public function view(User $user, Role $role): bool
    {
        return Abac::allows($user, $user->hasPermissionTo('roles.view'));
    }

    public function create(User $user): bool
    {
        return Abac::allows($user, $user->hasPermissionTo('roles.manage'));
    }

    public function update(User $user, Role $role): bool
    {
        return Abac::allows($user, $user->hasPermissionTo('roles.manage'));
    }

    public function delete(User $user, Role $role): bool
    {
        if (! Abac::allows($user, $user->hasPermissionTo('roles.manage'))) {
            return false;
        }

        $protected = config('authorization.protected_roles', []);

        return ! in_array($role->name, $protected, true);
    }
}

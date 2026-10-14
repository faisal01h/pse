<?php

namespace App\Policies;

use App\Authorization\Abac;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return Abac::allows($user, $user->hasPermissionTo('users.view'));
    }

    public function view(User $user, User $model): bool
    {
        return Abac::allows($user, $user->hasPermissionTo('users.view'));
    }

    public function update(User $user, User $model): bool
    {
        return Abac::allows($user, $user->hasPermissionTo('users.manage'));
    }
}

<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Spatie\Permission\PermissionRegistrar;

trait SeedsAuthorization
{
    protected function seedAuthorization(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(AuthorizationSeeder::class);
    }

    protected function makeAdmin(array $attributes = []): User
    {
        $this->seedAuthorization();

        $user = User::factory()->create($attributes);
        $user->assignRole(config('authorization.admin_role', 'admin'));

        return $user->fresh();
    }

    protected function makePlainUser(array $attributes = []): User
    {
        $this->seedAuthorization();

        return User::factory()->create($attributes);
    }
}

<?php

namespace Database\Factories;

use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! app()->runningUnitTests()) {
                return;
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            if (\Spatie\Permission\Models\Permission::query()->count() === 0) {
                (new AuthorizationSeeder)->run();
            }

            $admin = config('authorization.admin_role', 'admin');
            if (! $user->hasRole($admin)) {
                $user->assignRole($admin);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * User without roles or direct permissions (for authorization denial tests).
     */
    public function withoutAccess(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->syncRoles([]);
            $user->syncPermissions([]);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }
}

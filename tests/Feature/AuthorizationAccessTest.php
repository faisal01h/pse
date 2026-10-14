<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\SeedsAuthorization;
use Tests\TestCase;

class AuthorizationAccessTest extends TestCase
{
    use RefreshDatabase, SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_authorization_seeder_creates_admin_role_and_permissions(): void
    {
        $this->seedAuthorization();

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        foreach (array_keys(config('authorization.permissions')) as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }
    }

    public function test_admin_can_manage_roles_and_users(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'pemeriksa',
                'permissions' => ['roles.view'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['name' => 'pemeriksa']);

        $target = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.users.update', $target), [
                'is_active' => true,
                'roles' => ['pemeriksa'],
                'permissions' => [],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($target->fresh()->hasRole('pemeriksa'));
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        $user = $this->makePlainUser();

        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_inactive_admin_cannot_manage_access(): void
    {
        $admin = $this->makeAdmin(['is_active' => false]);
        $other = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($admin)
            ->post(route('admin.users.update', $other), [
                'is_active' => true,
                'roles' => ['admin'],
                'permissions' => [],
            ])
            ->assertForbidden();
    }

    public function test_cannot_deactivate_last_active_access_manager(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->post(route('admin.users.update', $admin), [
                'is_active' => false,
                'roles' => ['admin'],
                'permissions' => [],
            ])
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_cannot_delete_protected_admin_role(): void
    {
        $admin = $this->makeAdmin();
        $role = Role::findByName('admin');

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_cannot_strip_manage_permissions_from_admin_role(): void
    {
        $admin = $this->makeAdmin();
        $role = Role::findByName('admin');

        $this->actingAs($admin)
            ->from(route('admin.roles.edit', $role))
            ->post(route('admin.roles.update', $role), [
                'name' => 'admin',
                'permissions' => ['roles.view'],
            ])
            ->assertRedirect(route('admin.roles.edit', $role))
            ->assertSessionHasErrors('permissions');

        $this->assertTrue($role->fresh()->hasPermissionTo('roles.manage'));
        $this->assertTrue($role->fresh()->hasPermissionTo('users.manage'));
    }
}

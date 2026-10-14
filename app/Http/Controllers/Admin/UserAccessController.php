<?php

namespace App\Http\Controllers\Admin;

use App\Authorization\AccessGuard;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserAccessController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles:id,name', 'permissions:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name')->values(),
                'permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'permissionLabels' => config('authorization.permissions', []),
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('view', $user);

        $user->load(['roles:id,name', 'permissions:id,name']);

        return Inertia::render('Admin/UserAccessForm', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name')->values(),
                'permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            ],
            'roleOptions' => Role::query()->orderBy('name')->pluck('name')->values(),
            'permissionOptions' => collect(config('authorization.permissions', []))
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $permissionKeys = array_keys(config('authorization.permissions', []));
        $roleNames = Role::query()->pluck('name')->all();

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in($roleNames)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($permissionKeys)],
        ]);

        $roles = $validated['roles'] ?? [];
        $permissions = $validated['permissions'] ?? [];
        $isActive = (bool) $validated['is_active'];

        AccessGuard::assertNotStrandingLastManager($user, $isActive, $roles, $permissions);

        $user->is_active = $isActive;
        $user->save();
        $user->syncRoles($roles);
        $user->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.users.index');
    }
}

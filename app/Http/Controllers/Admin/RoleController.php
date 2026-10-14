<?php

namespace App\Http\Controllers\Admin;

use App\Authorization\AccessGuard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->values(),
                'protected' => in_array($role->name, config('authorization.protected_roles', []), true),
            ]);

        return Inertia::render('Admin/Roles', [
            'roles' => $roles,
            'permissionLabels' => config('authorization.permissions', []),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Admin/RoleForm', [
            'role' => null,
            'permissionOptions' => $this->permissionOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $validated = $this->validateRole($request);
        $permissions = $validated['permissions'] ?? [];

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('view', $role);

        $role->load('permissions:id,name');

        return Inertia::render('Admin/RoleForm', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'protected' => in_array($role->name, config('authorization.protected_roles', []), true),
            ],
            'permissionOptions' => $this->permissionOptions(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $validated = $this->validateRole($request, $role);
        $permissions = $validated['permissions'] ?? [];
        AccessGuard::assertAdminRoleKeepsManagePermissions($role, $permissions);

        if (! in_array($role->name, config('authorization.protected_roles', []), true)) {
            $role->name = $validated['name'];
            $role->save();
        }

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index');
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function permissionOptions(): array
    {
        $labels = config('authorization.permissions', []);

        return collect($labels)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, permissions?: list<string>}
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        $keys = array_keys(config('authorization.permissions', []));

        return $request->validate([
            'name' => [
                Rule::requiredIf(fn () => $role === null || ! in_array($role->name, config('authorization.protected_roles', []), true)),
                'string',
                'max:125',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($keys)],
        ], [
            'name.required' => 'Nama peran wajib diisi.',
            'name.unique' => 'Nama peran sudah dipakai.',
        ]);
    }
}

<?php

namespace App\Authorization;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalog
{
    /**
     * Ensure every ability in config/authorization.php exists in the database.
     * The admin UI reads labels from config; Spatie sync looks up rows by name.
     */
    public static function ensureRegistered(): void
    {
        foreach (array_keys(config('authorization.permissions', [])) as $key) {
            Permission::findOrCreate($key, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

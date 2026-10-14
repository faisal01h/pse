<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Abac
{
    public static function userMayAct(?User $user): bool
    {
        return $user !== null && $user->isActive();
    }

    /**
     * Resource is in a pending / unpublished review state.
     */
    public static function resourceIsPending(?Model $resource): bool
    {
        if ($resource === null || ! isset($resource->status)) {
            return false;
        }

        $status = $resource->status;

        if (is_string($status)) {
            return $status === 'pending';
        }

        return (int) $status === 0;
    }

    /**
     * Actor owns or last reviewed the resource.
     */
    public static function resourceOwnedBy(?Model $resource, User $user): bool
    {
        if ($resource === null) {
            return false;
        }

        foreach (['reviewed_by', 'user_id'] as $attribute) {
            if (isset($resource->{$attribute}) && (int) $resource->{$attribute} === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Combine permission-style intent with user + resource attributes.
     *
     * @param  callable(): bool|null  $resourceCheck
     */
    public static function allows(?User $user, bool $hasPermission, ?callable $resourceCheck = null): bool
    {
        if (! self::userMayAct($user) || ! $hasPermission) {
            return false;
        }

        if ($resourceCheck === null) {
            return true;
        }

        return (bool) $resourceCheck();
    }
}

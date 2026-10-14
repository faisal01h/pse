<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, RecordsChanges;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Active users who can manage roles or user access.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function activeAccessManagers()
    {
        return static::query()
            ->where('is_active', true)
            ->permission(['roles.manage', 'users.manage']);
    }

    public function isLastActiveAccessManager(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if (! $this->can('roles.manage') && ! $this->can('users.manage')) {
            return false;
        }

        return static::activeAccessManagers()->count() <= 1;
    }
}

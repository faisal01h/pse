<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SettingsRepository
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $this->cache = Setting::query()->pluck('value', 'key')->all();
        } catch (QueryException) {
            // Migrations may not have run yet (e.g. during early boot in tests).
            $this->cache = [];
        }

        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        return $all[$key];
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_string($value) ? $value : (string) ($value ?? $default);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        $this->cache = null;
    }

    /**
     * @param  array<string, mixed>  $pairs
     */
    public function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function setEncrypted(string $key, ?string $plain): void
    {
        if ($plain === null || $plain === '') {
            $this->set($key, null);

            return;
        }

        $this->set($key, Crypt::encryptString($plain));
    }

    public function getDecrypted(string $key, string $default = ''): string
    {
        $stored = $this->get($key);

        if (! is_string($stored) || $stored === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return $default;
        }
    }

    public function hasSecret(string $key): bool
    {
        $stored = $this->get($key);

        return is_string($stored) && $stored !== '';
    }

    public function forgetCache(): void
    {
        $this->cache = null;
    }
}

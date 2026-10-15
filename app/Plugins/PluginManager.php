<?php

namespace App\Plugins;

use App\Settings\SettingsRepository;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

class PluginManager
{
    /** @var array<string, Plugin> */
    private array $booted = [];

    public function __construct(
        private Application $app,
        private SettingsRepository $settings,
    ) {}

    public function bootRegistered(): void
    {
        foreach (config('plugins.registry', []) as $entry) {
            $class = $entry['class'] ?? null;
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            if (! ($entry['enabled'] ?? true)) {
                continue;
            }

            try {
                /** @var Plugin $plugin */
                $plugin = $this->app->make($class);
                $plugin->boot();
                $this->booted[$plugin->id()] = $plugin;
            } catch (Throwable) {
                // Keep the app up if a plugin fails to boot.
            }
        }
    }

    public function isEnabled(string $id): bool
    {
        foreach (config('plugins.registry', []) as $entry) {
            $class = $entry['class'] ?? null;
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            /** @var Plugin $plugin */
            $plugin = $this->app->make($class);
            if ($plugin->id() !== $id) {
                continue;
            }

            if (! ($entry['enabled'] ?? true)) {
                return false;
            }

            $settingKey = $entry['enabled_setting'] ?? null;
            if (is_string($settingKey) && $settingKey !== '') {
                return $this->settings->getBool($settingKey, false);
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<string, Plugin>
     */
    public function booted(): array
    {
        return $this->booted;
    }
}

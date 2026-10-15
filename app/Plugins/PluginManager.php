<?php

namespace App\Plugins;

use App\Plugins\AutoVet\AutoVetService;
use App\Plugins\Llm\LlmPlugin;
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
        $entry = $this->entryFor($id);
        if ($entry === null) {
            return false;
        }

        if (! ($entry['enabled'] ?? true)) {
            return false;
        }

        $settingKey = $entry['enabled_setting'] ?? null;
        if (is_string($settingKey) && $settingKey !== '' && ! $this->settings->getBool($settingKey, false)) {
            return false;
        }

        if (! $this->dependenciesMet($id)) {
            return false;
        }

        if ($id === 'llm') {
            /** @var LlmPlugin $llm */
            $llm = $this->app->make(LlmPlugin::class);

            return $llm->isConfigured();
        }

        if ($id === 'autovet') {
            return $this->app->make(AutoVetService::class)->isConfigured();
        }

        return true;
    }

    public function dependenciesMet(string $id): bool
    {
        $entry = $this->entryFor($id);
        if ($entry === null) {
            return false;
        }

        $requires = $entry['requires'] ?? [];
        if (! is_array($requires)) {
            return true;
        }

        foreach ($requires as $requiredId) {
            if (! is_string($requiredId) || ! $this->isEnabled($requiredId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entryFor(string $id): ?array
    {
        foreach (config('plugins.registry', []) as $entry) {
            $class = $entry['class'] ?? null;
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            try {
                /** @var Plugin $plugin */
                $plugin = $this->app->make($class);
            } catch (Throwable) {
                continue;
            }

            if ($plugin->id() === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<string, Plugin>
     */
    public function booted(): array
    {
        return $this->booted;
    }
}

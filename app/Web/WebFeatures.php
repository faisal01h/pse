<?php

namespace App\Web;

use App\Settings\SettingsRepository;

class WebFeatures
{
    public function __construct(private SettingsRepository $settings) {}

    public function submissionsEnabled(): bool
    {
        return $this->flag('web.submissions_enabled', true);
    }

    public function trackingEnabled(): bool
    {
        return $this->flag('web.tracking_enabled', true);
    }

    public function disputesEnabled(): bool
    {
        return $this->flag('web.disputes_enabled', true);
    }

    public function scipolEnabled(): bool
    {
        return $this->flag('web.scipol_enabled', true);
    }

    public function turnstileEnabled(): bool
    {
        return $this->flag('web.turnstile_enabled', true);
    }

    public function siteNotice(): string
    {
        return trim($this->settings->getString('web.site_notice', ''));
    }

    /**
     * @return array{
     *     submissions: bool,
     *     tracking: bool,
     *     disputes: bool,
     *     scipol: bool,
     *     turnstile: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'submissions' => $this->submissionsEnabled(),
            'tracking' => $this->trackingEnabled(),
            'disputes' => $this->disputesEnabled(),
            'scipol' => $this->scipolEnabled(),
            'turnstile' => $this->turnstileEnabled(),
        ];
    }

    public function isEnabled(string $feature): bool
    {
        return match ($feature) {
            'submissions' => $this->submissionsEnabled(),
            'tracking' => $this->trackingEnabled(),
            'disputes' => $this->disputesEnabled(),
            'scipol' => $this->scipolEnabled(),
            'turnstile' => $this->turnstileEnabled(),
            default => false,
        };
    }

    private function flag(string $key, bool $default): bool
    {
        if (! array_key_exists($key, $this->settings->all())) {
            return $default;
        }

        return $this->settings->getBool($key, $default);
    }
}

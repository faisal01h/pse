<?php

namespace App\Plugins\NewsRadar;

use App\Plugins\NewsRadar\Jobs\NewsRadarItemJob;
use App\Plugins\NewsRadar\Models\NewsRadarSighting;
use App\Plugins\PluginManager;
use App\Settings\SettingsRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NewsRadarService
{
    public const FREQUENCIES = [
        '15m' => 15,
        '1h' => 60,
        '6h' => 360,
        'daily' => 1440,
    ];

    public function __construct(
        private SettingsRepository $settings,
        private PluginManager $plugins,
        private RssFeedReader $feeds,
    ) {}

    public function intervalMinutes(): int
    {
        $frequency = $this->settings->getString('newsradar.frequency', '1h');
        if ($frequency === 'custom') {
            return max(5, $this->settings->getInt('newsradar.custom_minutes', 60));
        }

        return self::FREQUENCIES[$frequency] ?? 60;
    }

    public function isDue(?Carbon $now = null): bool
    {
        $now ??= now();
        $last = $this->settings->getString('newsradar.last_run_at', '');
        if ($last === '') {
            return true;
        }

        try {
            $lastAt = Carbon::parse($last);
        } catch (\Throwable) {
            return true;
        }

        return $lastAt->copy()->addMinutes($this->intervalMinutes())->lte($now);
    }

    /**
     * @return list<string>
     */
    public function feedUrls(): array
    {
        $feeds = $this->settings->get('newsradar.feeds', []);
        if (! is_array($feeds)) {
            return [];
        }

        return array_values(array_filter($feeds, fn ($url) => is_string($url) && $url !== ''));
    }

    /**
     * @return array{dispatched: int, feeds: int}
     */
    public function hunt(bool $force = false): array
    {
        if (! $this->plugins->isEnabled('newsradar')) {
            return ['dispatched' => 0, 'feeds' => 0];
        }

        if (! $force && ! $this->isDue()) {
            return ['dispatched' => 0, 'feeds' => 0];
        }

        $dispatched = 0;
        $feedUrls = $this->feedUrls();

        foreach ($feedUrls as $feedUrl) {
            foreach ($this->feeds->fetch($feedUrl) as $item) {
                $guid = $item['guid'] !== '' ? $item['guid'] : $item['link'];
                if ($guid === '') {
                    continue;
                }

                $created = false;
                DB::transaction(function () use ($feedUrl, $guid, $item, &$created) {
                    $feedHash = hash('sha256', $feedUrl);
                    $exists = NewsRadarSighting::query()
                        ->where('feed_url_hash', $feedHash)
                        ->where('guid', $guid)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    $sighting = NewsRadarSighting::query()->create([
                        'feed_url' => $feedUrl,
                        'feed_url_hash' => $feedHash,
                        'guid' => $guid,
                        'link' => $item['link'] !== '' ? $item['link'] : null,
                        'title' => $item['title'] !== '' ? $item['title'] : null,
                        'status' => NewsRadarSighting::STATUS_PENDING,
                    ]);
                    $created = true;
                    NewsRadarItemJob::dispatch($sighting->id);
                });

                if ($created) {
                    $dispatched++;
                }
            }
        }

        $this->settings->set('newsradar.last_run_at', now()->toIso8601String());

        return ['dispatched' => $dispatched, 'feeds' => count($feedUrls)];
    }
}

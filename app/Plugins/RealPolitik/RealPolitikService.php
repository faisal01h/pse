<?php

namespace App\Plugins\RealPolitik;

use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\Events\PowerSignalsCreated;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Plugins\RealPolitik\Models\SituationBrief;
use App\Settings\SettingsRepository;
use App\Models\Party;
use App\Models\Politician;
use App\Models\Topic;
use App\Plugins\RealPolitik\Models\WatchlistItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealPolitikService
{
    public function __construct(
        private SettingsRepository $settings,
        private PluginManager $plugins,
        private SignalDetector $shocks,
        private DriftDetector $drift,
        private CoalitionChangeDetector $coalitions,
        private SitrepAgent $sitrep,
    ) {}

    public function intervalMinutes(): int
    {
        return max(5, $this->settings->getInt('realpolitik.tick_minutes', 15));
    }

    public function isDue(?Carbon $now = null): bool
    {
        $now ??= now();
        $last = $this->settings->getString('realpolitik.last_run_at', '');
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
     * Start of the look-back range for change detectors: the later of the last
     * successful run and the configured window, capped at a week so downtime
     * is caught up without scanning unbounded history. Detectors dedupe by
     * fingerprint, so overlap is safe.
     */
    public function cursor(Carbon $now): Carbon
    {
        $windowStart = $now->copy()->subHours(max(1, $this->settings->getInt('realpolitik.window_hours', 24)));
        $floor = $now->copy()->subDays(7);
        $last = $this->settings->getString('realpolitik.last_run_at', '');

        try {
            $lastAt = $last !== '' ? Carbon::parse($last) : null;
        } catch (\Throwable) {
            $lastAt = null;
        }

        $since = $lastAt && $lastAt->lt($windowStart) ? $lastAt : $windowStart;

        return $since->lt($floor) ? $floor : $since;
    }

    /**
     * @return array{signals: int, brief_id: int|null}
     */
    public function tick(bool $force = false): array
    {
        if (! $this->plugins->isEnabled('realpolitik')) {
            return ['signals' => 0, 'brief_id' => null];
        }

        if (! $force && ! $this->isDue()) {
            return ['signals' => 0, 'brief_id' => null];
        }

        $now = now();
        $since = $this->cursor($now);

        $created = [];
        foreach ([
            'shocks' => fn () => $this->shocks->detectShocks($now),
            'drift' => fn () => $this->drift->detect($now, $since),
            'coalitions' => fn () => $this->coalitions->detect($now, $since),
        ] as $name => $detect) {
            try {
                array_push($created, ...$detect());
            } catch (Throwable $e) {
                Log::error("REALPOLITIK detector '{$name}' failed.", ['exception' => $e]);
            }
        }

        $brief = null;
        if ($this->settings->getBool('realpolitik.sitrep_enabled', true)) {
            try {
                $brief = $this->sitrep->generate();
            } catch (Throwable $e) {
                Log::error('REALPOLITIK sitrep failed.', ['exception' => $e]);
            }
        }

        $this->settings->set('realpolitik.last_run_at', $now->toIso8601String());

        if ($created !== []) {
            event(new PowerSignalsCreated(collect($created)->pluck('id')->all()));
        }

        return [
            'signals' => count($created),
            'brief_id' => $brief?->id,
        ];
    }

    /**
     * @param  Collection<int, WatchlistItem>|null  $watchlist  the viewer's watchlist; null for guests
     * @return list<array<string, mixed>>
     */
    public function presentSignals(int $limit = 40, ?Collection $watchlist = null, bool $onlyWatched = false): array
    {
        $watched = $this->watchedIds($watchlist);

        $signals = PowerSignal::query()
            ->open()
            ->with(['politician:id,name,party_id', 'party:id,name'])
            ->orderByDesc('created_at')
            ->limit($onlyWatched ? 300 : $limit)
            ->get()
            ->map(fn (PowerSignal $signal) => $this->presentSignal($signal) + [
                'watched' => $this->matchesWatchlist($signal, $watched),
            ]);

        if ($onlyWatched) {
            $signals = $signals->where('watched', true)->take($limit);
        }

        return $signals->values()->all();
    }

    /**
     * @param  Collection<int, WatchlistItem>|null  $watchlist
     * @return array{politician: array<int, true>, party: array<int, true>, topic: array<int, true>}
     */
    private function watchedIds(?Collection $watchlist): array
    {
        $ids = ['politician' => [], 'party' => [], 'topic' => []];
        foreach ($watchlist ?? [] as $item) {
            $key = match ($item->watchable_type) {
                Politician::class => 'politician',
                Party::class => 'party',
                Topic::class => 'topic',
                default => null,
            };
            if ($key !== null) {
                $ids[$key][(int) $item->watchable_id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param  array{politician: array<int, true>, party: array<int, true>, topic: array<int, true>}  $watched
     */
    private function matchesWatchlist(PowerSignal $signal, array $watched): bool
    {
        $evidence = $signal->evidence ?? [];
        $politicianIds = array_filter([$signal->politician_id, ...($evidence['politician_ids'] ?? [])]);
        $partyIds = array_filter([
            $signal->party_id,
            $signal->politician?->party_id,
            ...($evidence['party_ids'] ?? []),
        ]);

        foreach ($politicianIds as $id) {
            if (isset($watched['politician'][(int) $id])) {
                return true;
            }
        }
        foreach ($partyIds as $id) {
            if (isset($watched['party'][(int) $id])) {
                return true;
            }
        }

        return $signal->topic_id !== null && isset($watched['topic'][(int) $signal->topic_id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentBriefs(int $limit = 5): array
    {
        return SituationBrief::query()
            ->whereIn('status', [SituationBrief::STATUS_READY, SituationBrief::STATUS_LOW_CONFIDENCE])
            ->orderByDesc('window_end')
            ->limit($limit)
            ->get()
            ->map(fn (SituationBrief $brief) => [
                'id' => $brief->id,
                'window_start' => optional($brief->window_start)->toIso8601String(),
                'window_end' => optional($brief->window_end)->toIso8601String(),
                'summary_md' => $brief->summary_md,
                'payload' => $brief->payload,
                'status' => $brief->status,
                'confidence' => $brief->confidence,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSignal(PowerSignal $signal): array
    {
        return [
            'id' => $signal->id,
            'type' => $signal->type,
            'severity' => $signal->severity,
            'title' => $signal->title,
            'body' => $signal->body,
            'evidence' => $signal->evidence,
            'created_at' => optional($signal->created_at)->toIso8601String(),
            'politician' => $signal->politician ? [
                'id' => $signal->politician->id,
                'name' => $signal->politician->name,
                'href' => route('politicians.show', $signal->politician->id),
            ] : null,
            'party' => $signal->party ? [
                'id' => $signal->party->id,
                'name' => $signal->party->name,
                'href' => route('parties.show', $signal->party->id),
            ] : null,
        ];
    }
}

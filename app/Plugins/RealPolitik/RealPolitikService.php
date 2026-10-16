<?php

namespace App\Plugins\RealPolitik;

use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\Events\PowerSignalsCreated;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Plugins\RealPolitik\Models\SituationBrief;
use App\Settings\SettingsRepository;
use Carbon\Carbon;

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

        $created = [
            ...$this->shocks->detectShocks(),
            ...$this->drift->detect(),
            ...$this->coalitions->detect(),
        ];

        $brief = null;
        if ($this->settings->getBool('realpolitik.sitrep_enabled', true)) {
            $brief = $this->sitrep->generate();
        }

        $this->settings->set('realpolitik.last_run_at', now()->toIso8601String());

        if ($created !== []) {
            event(new PowerSignalsCreated(collect($created)->pluck('id')->all()));
        }

        return [
            'signals' => count($created),
            'brief_id' => $brief?->id,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentSignals(int $limit = 40): array
    {
        return PowerSignal::query()
            ->open()
            ->with(['politician:id,name', 'party:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (PowerSignal $signal) => $this->presentSignal($signal))
            ->all();
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

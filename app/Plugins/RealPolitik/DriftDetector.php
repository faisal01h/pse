<?php

namespace App\Plugins\RealPolitik;

use App\Models\IssueStance;
use App\Models\Politician;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Settings\SettingsRepository;
use Carbon\Carbon;

class DriftDetector
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * Emit signals when a stance interval was superseded recently.
     *
     * @return list<PowerSignal>
     */
    public function detect(?Carbon $now = null, ?Carbon $since = null): array
    {
        $now ??= now();
        $windowHours = max(1, $this->settings->getInt('realpolitik.window_hours', 24));
        $since ??= $now->copy()->subHours($windowHours);

        $closed = IssueStance::query()
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', $since->toDateString())
            ->whereNotNull('politician_id')
            ->with('issue')
            ->orderBy('ended_at')
            ->orderBy('id')
            ->get();

        $created = [];
        foreach ($closed as $previous) {
            $current = IssueStance::query()
                ->open()
                ->where('politician_id', $previous->politician_id)
                ->where('issue_id', $previous->issue_id)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if (! $current || $current->position === $previous->position) {
                continue;
            }

            if (PowerSignal::query()
                ->where('type', PowerSignal::TYPE_DRIFT)
                ->where('politician_id', $previous->politician_id)
                ->where('evidence->previous_stance_id', $previous->id)
                ->where('evidence->current_stance_id', $current->id)
                ->exists()) {
                continue;
            }

            $politician = Politician::query()->find($previous->politician_id);
            if (! $politician) {
                continue;
            }

            $issueName = $previous->issue?->name ?? 'isu';
            $created[] = PowerSignal::create([
                'type' => PowerSignal::TYPE_DRIFT,
                'severity' => PowerSignal::SEVERITY_MEDIUM,
                'politician_id' => $politician->id,
                'title' => "Pergeseran sikap: {$politician->name} — {$issueName}",
                'body' => sprintf(
                    'Posisi berubah dari %s menjadi %s (berlaku %s).',
                    $previous->positionLabel(),
                    $current->positionLabel(),
                    optional($current->effective_from)->toDateString() ?? 'baru'
                ),
                'evidence' => [
                    'previous_stance_id' => $previous->id,
                    'current_stance_id' => $current->id,
                    'issue_id' => $previous->issue_id,
                ],
            ]);
        }

        return $created;
    }
}

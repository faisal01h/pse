<?php

namespace App\Plugins\RealPolitik;

use App\Models\Politician;
use App\Models\PublicRecord;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Settings\SettingsRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SignalDetector
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @return list<PowerSignal>
     */
    public function detectShocks(?Carbon $now = null): array
    {
        $now ??= now();
        $windowHours = max(1, $this->settings->getInt('realpolitik.window_hours', 24));
        $baselineDays = max(7, $this->settings->getInt('realpolitik.baseline_days', 30));
        $minWindow = max(2, $this->settings->getInt('realpolitik.shock_min_count', 3));
        $ratioThreshold = max(1.5, $this->settings->getFloat('realpolitik.shock_ratio', 3.0));

        $windowStart = $now->copy()->subHours($windowHours);
        $baselineStart = $now->copy()->subDays($baselineDays);

        $windowCounts = $this->countsByPolitician($windowStart, $now);
        $baselineCounts = $this->countsByPolitician($baselineStart, $windowStart, false);

        $politicians = Politician::query()
            ->whereIn('id', array_keys($windowCounts))
            ->get()
            ->keyBy('id');

        $created = [];
        foreach ($windowCounts as $politicianId => $counts) {
            $windowTotal = (int) ($counts['total'] ?? 0);
            if ($windowTotal < $minWindow) {
                continue;
            }

            $baselineTotal = (int) ($baselineCounts[$politicianId]['total'] ?? 0);
            $baselineHours = max(1, $baselineStart->diffInHours($windowStart));
            $expected = ($baselineTotal / $baselineHours) * $windowHours;
            $ratio = $expected > 0 ? $windowTotal / $expected : $windowTotal;

            if ($ratio < $ratioThreshold && $expected > 0) {
                continue;
            }

            $allegationWindow = (int) ($counts['allegation'] ?? 0);
            $type = $allegationWindow >= max(2, (int) ceil($windowTotal * 0.5))
                ? PowerSignal::TYPE_ALLEGATION_SPIKE
                : PowerSignal::TYPE_SHOCK;

            if ($this->recentDuplicate($type, (int) $politicianId, $windowStart)) {
                continue;
            }

            $politician = $politicians->get($politicianId);
            if (! $politician) {
                continue;
            }

            $recordIds = PublicRecord::query()
                ->where('politician_id', $politicianId)
                ->where('status', PublicRecord::STATUS_PUBLISHED)
                ->where('created_at', '>=', $windowStart)
                ->orderByDesc('id')
                ->limit(8)
                ->pluck('id')
                ->all();

            $severity = $ratio >= ($ratioThreshold * 2) || $windowTotal >= ($minWindow * 2)
                ? PowerSignal::SEVERITY_HIGH
                : PowerSignal::SEVERITY_MEDIUM;

            $created[] = PowerSignal::create([
                'type' => $type,
                'severity' => $severity,
                'politician_id' => $politician->id,
                'title' => $type === PowerSignal::TYPE_ALLEGATION_SPIKE
                    ? "Lonjakan tuduhan: {$politician->name}"
                    : "Lonjakan liputan: {$politician->name}",
                'body' => sprintf(
                    '%d catatan publik dalam %d jam terakhir (rasio ~%sx baseline %d hari).',
                    $windowTotal,
                    $windowHours,
                    round($ratio, 1),
                    $baselineDays
                ),
                'evidence' => ['public_record_ids' => $recordIds],
            ]);
        }

        return $created;
    }

    /**
     * @return array<int, array{total: int, allegation: int}>
     */
    private function countsByPolitician(Carbon $from, Carbon $to, bool $toInclusive = true): array
    {
        $rows = PublicRecord::query()
            ->select([
                'politician_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN category = 'allegation' THEN 1 ELSE 0 END) as allegation"),
            ])
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->whereNotNull('politician_id')
            ->where('created_at', '>=', $from)
            ->where('created_at', $toInclusive ? '<=' : '<', $to)
            ->groupBy('politician_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->politician_id] = [
                'total' => (int) $row->total,
                'allegation' => (int) $row->allegation,
            ];
        }

        return $out;
    }

    private function recentDuplicate(string $type, int $politicianId, Carbon $since): bool
    {
        return PowerSignal::query()
            ->where('type', $type)
            ->where('politician_id', $politicianId)
            ->where('created_at', '>=', $since)
            ->exists();
    }
}

<?php

namespace App\Plugins\RealPolitik;

use App\Models\PartyLink;
use App\Models\PoliticianLink;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Settings\SettingsRepository;
use Carbon\Carbon;

class CoalitionChangeDetector
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @return list<PowerSignal>
     */
    public function detect(?Carbon $now = null): array
    {
        $now ??= now();
        $windowHours = max(1, $this->settings->getInt('realpolitik.window_hours', 24));
        $since = $now->copy()->subHours($windowHours);
        $created = [];

        $partyLinks = PartyLink::query()
            ->where(function ($query) use ($since) {
                $query->where('created_at', '>=', $since)
                    ->orWhere(function ($inner) use ($since) {
                        $inner->whereNotNull('ended_at')
                            ->where('ended_at', '>=', $since->toDateString());
                    });
            })
            ->with(['partyA:id,name', 'partyB:id,name'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($partyLinks as $link) {
            $created[] = $this->signalForPartyLink($link, $since);
        }

        $politicianLinks = PoliticianLink::query()
            ->where(function ($query) use ($since) {
                $query->where('created_at', '>=', $since)
                    ->orWhere(function ($inner) use ($since) {
                        $inner->whereNotNull('ended_at')
                            ->where('ended_at', '>=', $since->toDateString());
                    });
            })
            ->with(['politicianA:id,name', 'politicianB:id,name'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($politicianLinks as $link) {
            $created[] = $this->signalForPoliticianLink($link, $since);
        }

        return array_values(array_filter($created));
    }

    private function signalForPartyLink(PartyLink $link, Carbon $since): ?PowerSignal
    {
        $ended = $link->ended_at !== null;
        $fingerprint = 'party-'.$link->id.'-'.($ended ? 'end' : 'open');
        if ($this->seen($fingerprint, $since)) {
            return null;
        }

        $a = $link->partyA?->name ?? 'Partai';
        $b = $link->partyB?->name ?? 'Partai';
        $label = PartyLink::LABELS[$link->type] ?? $link->type;

        return PowerSignal::create([
            'type' => PowerSignal::TYPE_COALITION_CHANGE,
            'severity' => $link->type === PartyLink::TYPE_COALITION
                ? PowerSignal::SEVERITY_HIGH
                : PowerSignal::SEVERITY_MEDIUM,
            'party_id' => $link->party_a_id,
            'title' => $ended
                ? "Hubungan partai berakhir: {$a} — {$b}"
                : "Hubungan partai baru: {$a} — {$b}",
            'body' => sprintf(
                '%s (%s). Interval %s → %s.',
                $label,
                $ended ? 'ditutup' : 'dibuka',
                optional($link->effective_from)->toDateString() ?? '—',
                $ended ? (optional($link->ended_at)->toDateString() ?? '—') : 'sekarang'
            ),
            'evidence' => [
                'party_link_id' => $link->id,
                'fingerprint' => $fingerprint,
            ],
        ]);
    }

    private function signalForPoliticianLink(PoliticianLink $link, Carbon $since): ?PowerSignal
    {
        $ended = $link->ended_at !== null;
        $fingerprint = 'pol-'.$link->id.'-'.($ended ? 'end' : 'open');
        if ($this->seen($fingerprint, $since)) {
            return null;
        }

        $a = $link->politicianA?->name ?? 'Tokoh';
        $b = $link->politicianB?->name ?? 'Tokoh';
        $label = PoliticianLink::LABELS[$link->type] ?? $link->type;

        return PowerSignal::create([
            'type' => PowerSignal::TYPE_COALITION_CHANGE,
            'severity' => PowerSignal::SEVERITY_MEDIUM,
            'politician_id' => $link->politician_a_id,
            'title' => $ended
                ? "Hubungan tokoh berakhir: {$a} — {$b}"
                : "Hubungan tokoh baru: {$a} — {$b}",
            'body' => sprintf(
                '%s (%s). Interval %s → %s.',
                $label,
                $ended ? 'ditutup' : 'dibuka',
                optional($link->effective_from)->toDateString() ?? '—',
                $ended ? (optional($link->ended_at)->toDateString() ?? '—') : 'sekarang'
            ),
            'evidence' => [
                'politician_link_id' => $link->id,
                'fingerprint' => $fingerprint,
            ],
        ]);
    }

    private function seen(string $fingerprint, Carbon $since): bool
    {
        return PowerSignal::query()
            ->where('type', PowerSignal::TYPE_COALITION_CHANGE)
            ->where('created_at', '>=', $since)
            ->where('evidence->fingerprint', $fingerprint)
            ->exists();
    }
}

<?php

namespace App\Plugins\RealPolitik;

use App\Catalog\TemporalGraph;
use App\Models\BusinessAffiliation;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use Carbon\CarbonInterface;

class CuiBonoAnalyzer
{
    public function __construct(private TemporalGraph $temporal) {}

    /**
     * @return array{
     *   record: array<string, mixed>,
     *   as_of: string,
     *   beneficiaries: list<array<string, mixed>>,
     *   exposed: list<array<string, mixed>>
     * }
     */
    public function forRecord(PublicRecord $record): array
    {
        $asOf = $record->occurred_on?->toDateString()
            ?? optional($record->reviewed_at)->toDateString()
            ?? optional($record->created_at)->toDateString()
            ?? now()->toDateString();

        $politicianId = (int) $record->politician_id;
        $links = $this->temporal->politicianLinksAsOf($asOf, [$politicianId]);
        $affiliations = BusinessAffiliation::query()
            ->where('politician_id', $politicianId)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->with('business:id,name,sector')
            ->orderBy('id')
            ->get();

        $beneficiaries = [];
        $exposed = [];

        foreach ($links as $link) {
            /** @var PoliticianLink $link */
            $other = $link->politician_a_id === $politicianId ? $link->politicianB : $link->politicianA;
            if (! $other) {
                continue;
            }

            $entry = [
                'kind' => 'politician',
                'id' => $other->id,
                'name' => $other->name,
                'href' => route('politicians.show', $other->id),
                'reason' => $link->labelFrom($politicianId).' (as of '.$asOf.')',
                'link_type' => $link->type,
            ];

            if (in_array($link->type, [PoliticianLink::TYPE_ALLY, PoliticianLink::TYPE_SUPPORT], true)) {
                $beneficiaries[] = $entry;
            }
            if ($link->type === PoliticianLink::TYPE_RIVAL) {
                $exposed[] = $entry;
            }
        }

        $politician = Politician::query()->with('party:id,name')->find($politicianId);
        if ($politician?->party_id) {
            $partyLinks = $this->temporal->partyLinksAsOf($asOf, (int) $politician->party_id);
            foreach ($partyLinks as $link) {
                /** @var PartyLink $link */
                $other = $link->party_a_id === $politician->party_id ? $link->partyB : $link->partyA;
                if (! $other) {
                    continue;
                }
                $entry = [
                    'kind' => 'party',
                    'id' => $other->id,
                    'name' => $other->name,
                    'href' => route('parties.show', $other->id),
                    'reason' => $link->labelFrom((int) $politician->party_id).' (as of '.$asOf.')',
                    'link_type' => $link->type,
                ];
                if ($link->type === PartyLink::TYPE_COALITION) {
                    $beneficiaries[] = $entry;
                }
                if ($link->type === PartyLink::TYPE_OPPOSITION) {
                    $exposed[] = $entry;
                }
            }
        }

        foreach ($affiliations as $affiliation) {
            if (! $affiliation->business) {
                continue;
            }
            $beneficiaries[] = [
                'kind' => 'business',
                'id' => $affiliation->business->id,
                'name' => $affiliation->business->name,
                'href' => route('businesses.show', $affiliation->business->id),
                'reason' => $affiliation->typeLabel().' tercatat pada tokoh terkait',
                'link_type' => $affiliation->type,
            ];
        }

        return [
            'record' => [
                'id' => $record->id,
                'href' => route('records.show', $record),
                'politician' => $politician?->name,
                'category' => $record->category,
            ],
            'as_of' => $asOf,
            'beneficiaries' => array_values($beneficiaries),
            'exposed' => array_values($exposed),
        ];
    }
}

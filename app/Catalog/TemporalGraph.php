<?php

namespace App\Catalog;

use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\PartyLink;
use App\Models\PoliticianLink;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TemporalGraph
{
    /**
     * @return Builder<IssueStance>
     */
    public function stancesQuery(?int $politicianId = null, ?int $partyId = null): Builder
    {
        $query = IssueStance::query();
        if ($politicianId) {
            $query->where('politician_id', $politicianId);
        }
        if ($partyId) {
            $query->where('party_id', $partyId);
        }

        return $query;
    }

    /**
     * @return Collection<int, IssueStance>
     */
    public function currentStances(?int $politicianId = null, ?int $partyId = null): Collection
    {
        return $this->dedupeByNaturalKey(
            $this->stancesQuery($politicianId, $partyId)->open()->with('issue')->orderByDesc('effective_from')->orderByDesc('id')->get(),
            fn (IssueStance $stance) => ($stance->politician_id ?: 'p'.$stance->party_id).'|'.$stance->issue_id
        );
    }

    /**
     * @return Collection<int, IssueStance>
     */
    public function stancesAsOf(CarbonInterface|string $date, ?int $politicianId = null, ?int $partyId = null): Collection
    {
        return $this->dedupeByNaturalKey(
            $this->stancesQuery($politicianId, $partyId)->asOf($date)->with('issue')->orderByDesc('effective_from')->orderByDesc('id')->get(),
            fn (IssueStance $stance) => ($stance->politician_id ?: 'p'.$stance->party_id).'|'.$stance->issue_id
        );
    }

    /**
     * @return Collection<int, IssueStance>
     */
    public function stanceHistory(?int $politicianId, ?int $partyId, ?int $issueId = null): Collection
    {
        $query = $this->stancesQuery($politicianId, $partyId)->with('issue')
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($issueId) {
            $query->where('issue_id', $issueId);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, IdeologyPosition>
     */
    public function currentTendencies(?int $politicianId = null, ?int $partyId = null): Collection
    {
        $query = IdeologyPosition::query();
        if ($politicianId) {
            $query->where('politician_id', $politicianId);
        }
        if ($partyId) {
            $query->where('party_id', $partyId);
        }

        return $this->dedupeByNaturalKey(
            $query->open()->orderByDesc('effective_from')->orderByDesc('id')->get(),
            fn (IdeologyPosition $position) => ($position->politician_id ?: 'p'.$position->party_id).'|'.$position->axis
        );
    }

    /**
     * @return Collection<int, IdeologyPosition>
     */
    public function tendenciesAsOf(CarbonInterface|string $date, ?int $politicianId = null, ?int $partyId = null): Collection
    {
        $query = IdeologyPosition::query();
        if ($politicianId) {
            $query->where('politician_id', $politicianId);
        }
        if ($partyId) {
            $query->where('party_id', $partyId);
        }

        return $this->dedupeByNaturalKey(
            $query->asOf($date)->orderByDesc('effective_from')->orderByDesc('id')->get(),
            fn (IdeologyPosition $position) => ($position->politician_id ?: 'p'.$position->party_id).'|'.$position->axis
        );
    }

    /**
     * @return Collection<int, PoliticianLink>
     */
    public function currentPoliticianLinks(iterable $politicianIds): Collection
    {
        $ids = collect($politicianIds)->filter()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return PoliticianLink::query()
            ->open()
            ->where(function (Builder $query) use ($ids) {
                $query->whereIn('politician_a_id', $ids)->orWhereIn('politician_b_id', $ids);
            })
            ->with(['politicianA:id,name,status', 'politicianB:id,name,status'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, PoliticianLink>
     */
    public function politicianLinksAsOf(CarbonInterface|string $date, iterable $politicianIds): Collection
    {
        $ids = collect($politicianIds)->filter()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return PoliticianLink::query()
            ->asOf($date)
            ->where(function (Builder $query) use ($ids) {
                $query->whereIn('politician_a_id', $ids)->orWhereIn('politician_b_id', $ids);
            })
            ->with(['politicianA:id,name,status', 'politicianB:id,name,status'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, PartyLink>
     */
    public function currentPartyLinks(int $partyId): Collection
    {
        return PartyLink::query()
            ->open()
            ->where(function (Builder $query) use ($partyId) {
                $query->where('party_a_id', $partyId)->orWhere('party_b_id', $partyId);
            })
            ->with(['partyA:id,name', 'partyB:id,name'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, PartyLink>
     */
    public function partyLinksAsOf(CarbonInterface|string $date, int $partyId): Collection
    {
        return PartyLink::query()
            ->asOf($date)
            ->where(function (Builder $query) use ($partyId) {
                $query->where('party_a_id', $partyId)->orWhere('party_b_id', $partyId);
            })
            ->with(['partyA:id,name', 'partyB:id,name'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, PoliticianLink>
     */
    public function politicianLinkHistory(int $politicianId): Collection
    {
        return PoliticianLink::query()
            ->where(function (Builder $query) use ($politicianId) {
                $query->where('politician_a_id', $politicianId)->orWhere('politician_b_id', $politicianId);
            })
            ->with(['politicianA:id,name,status', 'politicianB:id,name,status'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, PartyLink>
     */
    public function partyLinkHistory(int $partyId): Collection
    {
        return PartyLink::query()
            ->where(function (Builder $query) use ($partyId) {
                $query->where('party_a_id', $partyId)->orWhere('party_b_id', $partyId);
            })
            ->with(['partyA:id,name', 'partyB:id,name'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $rows
     * @param  callable(T): string  $key
     * @return Collection<int, T>
     */
    private function dedupeByNaturalKey(Collection $rows, callable $key): Collection
    {
        return $rows->unique(fn ($row) => $key($row))->values();
    }
}

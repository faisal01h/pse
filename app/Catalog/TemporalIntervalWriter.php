<?php

namespace App\Catalog;

use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\PartyLink;
use App\Models\PoliticianLink;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TemporalIntervalWriter
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function supersedeStance(array $attributes): IssueStance
    {
        return DB::transaction(function () use ($attributes) {
            $effectiveFrom = $this->resolveEffectiveFrom($attributes);
            $query = IssueStance::query()->where('issue_id', $attributes['issue_id']);
            if (! empty($attributes['politician_id'])) {
                $query->where('politician_id', $attributes['politician_id']);
            } else {
                $query->where('party_id', $attributes['party_id']);
            }

            $this->closeOpenIntervals($query, $effectiveFrom);

            return IssueStance::create([
                ...$attributes,
                'effective_from' => $effectiveFrom->toDateString(),
                'ended_at' => null,
            ]);
        });
    }

    /**
     * Manual tendency write: close prior open axis row and insert a new interval.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function supersedeTendency(array $attributes): IdeologyPosition
    {
        return DB::transaction(function () use ($attributes) {
            $effectiveFrom = $this->resolveEffectiveFrom($attributes);
            $query = IdeologyPosition::query()->where('axis', $attributes['axis']);
            if (! empty($attributes['politician_id'])) {
                $query->where('politician_id', $attributes['politician_id']);
            } else {
                $query->where('party_id', $attributes['party_id']);
            }

            $this->closeOpenIntervals($query, $effectiveFrom);

            return IdeologyPosition::create([
                ...$attributes,
                'samples' => $attributes['samples'] ?? 1,
                'effective_from' => $effectiveFrom->toDateString(),
                'ended_at' => null,
            ]);
        });
    }

    /**
     * Proposal blend: update the open ideology interval in place, or open a new one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function blendTendency(array $attributes, int $incomingScore, int $recordId): IdeologyPosition
    {
        return DB::transaction(function () use ($attributes, $incomingScore, $recordId) {
            $effectiveFrom = $this->resolveEffectiveFrom($attributes);
            $query = IdeologyPosition::query()
                ->where('politician_id', $attributes['politician_id'])
                ->where('axis', $attributes['axis'])
                ->open()
                ->orderByDesc('effective_from')
                ->orderByDesc('id');

            $position = $query->first();
            if (! $position) {
                $position = IdeologyPosition::create([
                    ...$attributes,
                    'score' => max(0, min(100, $incomingScore)),
                    'samples' => 1,
                    'effective_from' => $effectiveFrom->toDateString(),
                    'ended_at' => null,
                ]);
            } else {
                $blended = IdeologyPosition::blendScore(
                    (int) $position->score,
                    (int) ($position->samples ?: 1),
                    $incomingScore
                );
                $position->fill([
                    'score' => $blended['score'],
                    'samples' => $blended['samples'],
                    'note' => $attributes['note'],
                    'source_url' => $attributes['source_url'],
                    'source_type' => $attributes['source_type'],
                    'occurred_on' => $attributes['occurred_on'] ?? $position->occurred_on,
                    'user_id' => $attributes['user_id'] ?? $position->user_id,
                ])->save();
            }

            $position->basisRecords()->syncWithoutDetaching([$recordId]);

            return $position->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function openPoliticianLink(array $attributes): PoliticianLink
    {
        return DB::transaction(function () use ($attributes) {
            $effectiveFrom = $this->resolveEffectiveFrom($attributes);
            $exists = PoliticianLink::query()
                ->where('politician_a_id', $attributes['politician_a_id'])
                ->where('politician_b_id', $attributes['politician_b_id'])
                ->where('type', $attributes['type'])
                ->open()
                ->exists();

            if ($exists) {
                throw new TemporalDuplicateException('Hubungan ini sudah tercatat.');
            }

            return PoliticianLink::create([
                ...$attributes,
                'effective_from' => $effectiveFrom->toDateString(),
                'ended_at' => null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function openPartyLink(array $attributes): PartyLink
    {
        return DB::transaction(function () use ($attributes) {
            $effectiveFrom = $this->resolveEffectiveFrom($attributes);
            $exists = PartyLink::query()
                ->where('party_a_id', $attributes['party_a_id'])
                ->where('party_b_id', $attributes['party_b_id'])
                ->where('type', $attributes['type'])
                ->open()
                ->exists();

            if ($exists) {
                throw new TemporalDuplicateException('Hubungan ini sudah tercatat.');
            }

            return PartyLink::create([
                ...$attributes,
                'effective_from' => $effectiveFrom->toDateString(),
                'ended_at' => null,
            ]);
        });
    }

    public function endModel(Model $model, ?CarbonInterface $endedAt = null): Model
    {
        if ($model->ended_at !== null) {
            return $model;
        }

        $ended = $endedAt ? Carbon::parse($endedAt)->startOfDay() : now()->startOfDay();
        $from = Carbon::parse($model->effective_from)->startOfDay();
        if ($ended->lt($from)) {
            $ended = $from->copy();
        }

        $model->ended_at = $ended->toDateString();
        $model->save();

        return $model;
    }

    public function endOpenTendencies(?int $politicianId, ?int $partyId, string $axis): void
    {
        $query = IdeologyPosition::query()->where('axis', $axis)->open();
        $politicianId
            ? $query->where('politician_id', $politicianId)
            : $query->where('party_id', $partyId);

        $query->get()->each(fn (IdeologyPosition $position) => $this->endModel($position));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function closeOpenIntervals(Builder $query, Carbon $effectiveFrom): void
    {
        $openRows = (clone $query)->open()->orderBy('id')->get();
        foreach ($openRows as $row) {
            $from = Carbon::parse($row->effective_from)->startOfDay();
            $end = $effectiveFrom->copy()->subDay();
            if ($end->lt($from)) {
                $end = $effectiveFrom->copy();
            }
            $row->ended_at = $end->toDateString();
            $row->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveEffectiveFrom(array $attributes): Carbon
    {
        $raw = $attributes['effective_from'] ?? $attributes['occurred_on'] ?? null;
        if ($raw) {
            return Carbon::parse($raw)->startOfDay();
        }

        return now()->startOfDay();
    }
}

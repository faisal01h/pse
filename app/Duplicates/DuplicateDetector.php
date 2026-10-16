<?php

namespace App\Duplicates;

use App\Models\Business;
use App\Models\Party;
use App\Models\Politician;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DuplicateDetector
{
    public const SIMILAR_THRESHOLD = 0.85;

    public function __construct(private readonly NameNormalizer $normalizer) {}

    /**
     * @return class-string<Model>
     */
    public static function modelFor(string $type): string
    {
        return match ($type) {
            NameNormalizer::POLITICIAN => Politician::class,
            NameNormalizer::PARTY => Party::class,
            NameNormalizer::BUSINESS => Business::class,
            default => throw new InvalidArgumentException('Jenis data tidak dikenal.'),
        };
    }

    public static function types(): array
    {
        return [NameNormalizer::POLITICIAN, NameNormalizer::PARTY, NameNormalizer::BUSINESS];
    }

    /**
     * Records whose normalized name equals the given name.
     *
     * @return Collection<int, Model>
     */
    public function exactMatches(string $type, string $name, ?int $ignoreId = null): Collection
    {
        $needle = $this->normalizer->normalize($type, $name);

        if ($needle === '') {
            return collect();
        }

        $model = self::modelFor($type);

        return $model::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->orderBy('id')
            ->get()
            ->filter(fn (Model $row) => $this->normalizer->normalize($type, (string) $row->name) === $needle)
            ->values();
    }

    public function firstExact(string $type, string $name, ?int $ignoreId = null): ?Model
    {
        return $this->exactMatches($type, $name, $ignoreId)->first();
    }

    /**
     * Existing profile for a public submission: same normalized name and same institution.
     */
    public function findPolitician(string $name, string $institution): ?Politician
    {
        $institution = $this->normalizer->normalize(NameNormalizer::PARTY, $institution);

        return $this->exactMatches(NameNormalizer::POLITICIAN, $name)
            ->first(fn (Politician $row) => $this->normalizer->normalize(NameNormalizer::PARTY, (string) $row->institution) === $institution);
    }

    /**
     * Closest fuzzy (non-identical) candidates for a name, best first.
     *
     * @return Collection<int, array{record: Model, score: float}>
     */
    public function similarTo(string $type, string $name, ?int $ignoreId = null, int $limit = 5): Collection
    {
        $needle = $this->normalizer->normalize($type, $name);
        $model = self::modelFor($type);

        return $model::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get()
            ->map(fn (Model $row) => [
                'record' => $row,
                'score' => $this->normalizer->similarity($needle, $this->normalizer->normalize($type, (string) $row->name)),
            ])
            ->filter(fn (array $hit) => $hit['score'] >= self::SIMILAR_THRESHOLD)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Candidate duplicate pairs for admins. "exact" pairs share a normalized name;
     * "similar" pairs only pass the fuzzy threshold.
     *
     * @return array<int, array{kind: string, score: float, a: Model, b: Model}>
     */
    public function pairs(string $type): array
    {
        $model = self::modelFor($type);
        $rows = $model::query()->orderBy('id')->get();

        $normalized = [];
        $tokenIndex = [];
        foreach ($rows as $row) {
            $key = $this->normalizer->normalize($type, (string) $row->name);
            $normalized[$row->id] = $key;

            foreach (array_unique(explode(' ', $key)) as $token) {
                if (strlen($token) >= 4) {
                    $tokenIndex[$token][] = $row->id;
                }
            }
        }

        $byId = $rows->keyBy('id');
        $pairs = [];
        $seen = [];

        $consider = function (int $first, int $second) use (&$pairs, &$seen, $normalized, $byId) {
            [$low, $high] = $first < $second ? [$first, $second] : [$second, $first];
            $pairKey = $low.'-'.$high;

            if (isset($seen[$pairKey])) {
                return;
            }
            $seen[$pairKey] = true;

            $score = $this->normalizer->similarity($normalized[$low], $normalized[$high]);

            if ($score < self::SIMILAR_THRESHOLD) {
                return;
            }

            $pairs[] = [
                'kind' => $normalized[$low] === $normalized[$high] ? 'exact' : 'similar',
                'score' => $score,
                'a' => $byId[$low],
                'b' => $byId[$high],
            ];
        };

        // Identical normalized names (also catches short names the token index skips).
        $groups = [];
        foreach ($normalized as $id => $key) {
            if ($key !== '') {
                $groups[$key][] = $id;
            }
        }
        foreach ($groups as $ids) {
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $consider($ids[$i], $ids[$j]);
                }
            }
        }

        // Fuzzy: only compare names that share a meaningful token.
        foreach ($tokenIndex as $ids) {
            if (count($ids) > 200) {
                continue;
            }
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $consider($ids[$i], $ids[$j]);
                }
            }
        }

        usort($pairs, fn ($x, $y) => [$y['kind'] === 'exact', $y['score']] <=> [$x['kind'] === 'exact', $x['score']]);

        return $pairs;
    }
}

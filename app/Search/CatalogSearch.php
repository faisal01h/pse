<?php

namespace App\Search;

use App\Models\SearchDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class CatalogSearch
{
    public function __construct(private Embedder $embedder) {}

    /**
     * @return array<int, array{type: string, label: string, title: string, subtitle: ?string, href: string, image_url: ?string}>
     */
    public function search(string $raw, int $limit = 12): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');

        if (mb_strlen($query) < 2) {
            return [];
        }

        $query = mb_substr($query, 0, 200);
        $tokens = $this->tokens($query);

        if ($tokens === []) {
            return [];
        }

        $fulltext = $this->fulltextScores($query);
        $documents = SearchDocument::query()->orderBy('id')->get();
        $queryVector = $this->queryVector($query);
        $threshold = $this->threshold();
        $maxFulltext = max($fulltext ?: [0]);
        $hits = [];

        foreach ($documents as $document) {
            $lexical = $this->lexicalScore($query, $tokens, $document);
            $fulltextScore = $fulltext[$document->id] ?? 0.0;

            if ($maxFulltext > 0 && $fulltextScore > 0) {
                $lexical = min(1.0, max($lexical, $fulltextScore / $maxFulltext));
            }

            $cosine = $this->semanticScore($queryVector, $query, $document);
            $score = $this->blend($lexical, $cosine, $threshold);

            if ($score === null) {
                continue;
            }

            $hits[] = [
                'score' => $score,
                'type_rank' => match ($document->type) {
                    SearchDocument::TYPE_POLITICIAN => 0,
                    SearchDocument::TYPE_PARTY => 1,
                    SearchDocument::TYPE_BUSINESS => 2,
                    default => 3,
                },
                'type' => $document->type,
                'label' => $document->label,
                'title' => $document->title,
                'subtitle' => $document->subtitle,
                'href' => $document->href,
                'image_url' => $document->image_url,
            ];
        }

        usort($hits, function (array $left, array $right) {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            if ($left['type_rank'] !== $right['type_rank']) {
                return $left['type_rank'] <=> $right['type_rank'];
            }

            return strcmp($left['title'], $right['title']);
        });

        return array_map(function (array $hit) {
            unset($hit['score'], $hit['type_rank']);

            return $hit;
        }, array_slice($hits, 0, $limit));
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function lexicalScore(string $query, array $tokens, SearchDocument $document): float
    {
        $haystack = mb_strtolower($document->title.' '.$document->body);
        $title = mb_strtolower($document->title);
        $matched = 0;
        $titleHits = 0;

        foreach ($tokens as $token) {
            if (! $this->tokenMatches($token, $haystack)) {
                continue;
            }

            $matched++;

            if ($this->tokenMatches($token, $title)) {
                $titleHits++;
            }
        }

        if ($matched === 0) {
            return 0.0;
        }

        $coverage = $matched / count($tokens);
        $titleBoost = min(0.3, $titleHits * 0.15);
        $phrase = mb_strtolower($query);
        $phraseBoost = mb_strpos($haystack, $phrase) !== false ? 0.2 : 0.0;

        return min(1.0, $coverage + $titleBoost + $phraseBoost);
    }

    private function tokenMatches(string $token, string $haystack): bool
    {
        if (mb_strpos($haystack, $token) !== false) {
            return true;
        }

        if (mb_strlen($token) < 5) {
            return false;
        }

        return (bool) preg_match('/(?:^|[^\p{L}\p{N}])'.preg_quote($token, '/').'\p{L}*/u', $haystack);
    }

    /**
     * @param  array<int, float>|null  $queryVector
     */
    private function semanticScore(?array $queryVector, string $query, SearchDocument $document): float
    {
        $cosine = $this->cosine($queryVector, $document);

        if ($queryVector === null || ! $this->embedder instanceof HashingEmbedder) {
            return $cosine;
        }

        return max($cosine, $this->embedder->maxWordCosine($queryVector, $query, $document->title.' '.$document->body));
    }

    /**
     * @param  array<int, float>|null  $queryVector
     */
    private function cosine(?array $queryVector, SearchDocument $document): float
    {
        if ($queryVector === null || ! is_array($document->embedding) || $document->embedding === []) {
            return 0.0;
        }

        if ($document->embedding_model !== $this->embedder->model()) {
            return 0.0;
        }

        $length = min(count($queryVector), count($document->embedding));
        $dot = 0.0;

        for ($index = 0; $index < $length; $index++) {
            $dot += $queryVector[$index] * (float) $document->embedding[$index];
        }

        return $dot;
    }

    private function blend(float $lexical, float $cosine, float $threshold): ?float
    {
        $lexicalWeight = (float) config('search.lexical_weight');
        $semanticWeight = (float) config('search.semantic_weight');

        if ($lexical > 0 && $cosine > 0) {
            return ($lexicalWeight * $lexical) + ($semanticWeight * max(0.0, $cosine));
        }

        if ($lexical > 0) {
            return $lexical;
        }

        if ($cosine >= $threshold) {
            return $semanticWeight * $cosine;
        }

        return null;
    }

    /**
     * @return array<int, float>
     */
    private function queryVector(string $query): ?array
    {
        try {
            return $this->embedder->embed($query);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function threshold(): float
    {
        $configured = config('search.semantic_threshold');

        if ($configured === null || $configured === '') {
            return $this->embedder->semanticThreshold();
        }

        return (float) $configured;
    }

    /**
     * @return array<int, float>
     */
    private function fulltextScores(string $query): array
    {
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'mysql') {
                return $this->mysqlScores($query);
            }

            if ($driver === 'pgsql') {
                return $this->pgsqlScores($query);
            }
        } catch (QueryException) {
            return [];
        }

        return [];
    }

    /**
     * @return array<int, float>
     */
    private function mysqlScores(string $query): array
    {
        $scores = [];
        $rows = DB::select(
            'select id, MATCH(title, body) AGAINST (? IN NATURAL LANGUAGE MODE) as score
             from search_documents
             where MATCH(title, body) AGAINST (? IN NATURAL LANGUAGE MODE)',
            [$query, $query]
        );

        foreach ($rows as $row) {
            $scores[(int) $row->id] = (float) $row->score;
        }

        $boolean = $this->booleanQuery($query);

        if ($boolean === null) {
            return $scores;
        }

        $prefixRows = DB::select(
            'select id, MATCH(title, body) AGAINST (? IN BOOLEAN MODE) as score
             from search_documents
             where MATCH(title, body) AGAINST (? IN BOOLEAN MODE)',
            [$boolean, $boolean]
        );

        foreach ($prefixRows as $row) {
            $id = (int) $row->id;
            $scores[$id] = max($scores[$id] ?? 0.0, (float) $row->score);
        }

        return $scores;
    }

    /**
     * @return array<int, float>
     */
    private function pgsqlScores(string $query): array
    {
        $rows = DB::select(
            "select id, ts_rank_cd(to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(body, '')), plainto_tsquery('simple', ?)) as score
             from search_documents
             where to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(body, '')) @@ plainto_tsquery('simple', ?)",
            [$query, $query]
        );

        $scores = [];

        foreach ($rows as $row) {
            $scores[(int) $row->id] = (float) $row->score;
        }

        return $scores;
    }

    private function booleanQuery(string $query): ?string
    {
        $parts = [];

        foreach ($this->tokens($query) as $token) {
            if (mb_strlen($token) < 3) {
                continue;
            }

            $parts[] = $token.'*';
        }

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $query): array
    {
        $query = mb_strtolower($query);
        $query = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $query) ?? '';
        $parts = preg_split('/\s+/u', trim($query)) ?: [];

        return array_values(array_unique(array_filter($parts, function ($token) {
            return mb_strlen($token) >= 2;
        })));
    }
}

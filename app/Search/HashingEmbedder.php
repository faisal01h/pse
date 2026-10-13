<?php

namespace App\Search;

class HashingEmbedder implements Embedder
{
    public function __construct(private int $dimensions = 256)
    {
        $this->dimensions = max(32, $dimensions);
    }

    public function model(): string
    {
        return 'hashing-v2-'.$this->dimensions;
    }

    public function semanticThreshold(): float
    {
        return 0.45;
    }

    /**
     * Highest cosine between the query and a single word in the text.
     *
     * A full-document vector gets diluted by unrelated words. Word-level
     * comparison keeps morphological neighbors such as korupsi / koruptor.
     *
     * @param  array<int, float>  $queryVector
     */
    public function maxWordCosine(array $queryVector, string $query, string $text): float
    {
        $needles = $this->trigramSet($this->words($query));

        if ($needles === []) {
            return 0.0;
        }

        $best = 0.0;

        foreach ($this->words($text) as $word) {
            if (mb_strlen($word) < 4 || ! $this->sharesTrigram($word, $needles)) {
                continue;
            }

            $best = max($best, $this->dot($queryVector, $this->embed($word)));
        }

        return $best;
    }

    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        foreach ($this->features($text) as $feature => $weight) {
            $slot = $this->slot($feature);
            $vector[$slot] += $weight * $this->sign($feature);
        }

        return $this->normalize($vector);
    }

    /**
     * @return array<string, float>
     */
    private function features(string $text): array
    {
        $words = $this->words($text);
        $features = [];

        $count = count($words);

        foreach ($words as $index => $word) {
            $features['w:'.$word] = ($features['w:'.$word] ?? 0) + 0.45;

            if ($index < $count - 1) {
                $bigram = 'b:'.$word.' '.$words[$index + 1];
                $features[$bigram] = ($features[$bigram] ?? 0) + 0.5;
            }

            if (mb_strlen($word) < 4) {
                continue;
            }

            foreach ($this->trigrams($word) as $trigram) {
                $features[$trigram] = ($features[$trigram] ?? 0) + 1.0;
            }
        }

        return $features;
    }

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/u', trim($text)) ?: [];

        return array_values(array_filter($parts, function ($word) {
            return mb_strlen($word) >= 2;
        }));
    }

    /**
     * @param  array<int, string>  $words
     * @return array<string, true>
     */
    private function trigramSet(array $words): array
    {
        $set = [];

        foreach ($words as $word) {
            foreach ($this->trigrams($word) as $trigram) {
                $set[$trigram] = true;
            }
        }

        return $set;
    }

    /**
     * @param  array<string, true>  $needles
     */
    private function sharesTrigram(string $word, array $needles): bool
    {
        foreach ($this->trigrams($word) as $trigram) {
            if (isset($needles[$trigram])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function trigrams(string $word): array
    {
        if (mb_strlen($word) < 4) {
            return [];
        }

        $padded = ' '.$word.' ';
        $length = mb_strlen($padded);
        $trigrams = [];

        for ($index = 0; $index < $length - 2; $index++) {
            $trigrams[] = 't:'.mb_substr($padded, $index, 3);
        }

        return $trigrams;
    }

    /**
     * @param  array<int, float>  $left
     * @param  array<int, float>  $right
     */
    private function dot(array $left, array $right): float
    {
        $dot = 0.0;
        $length = min(count($left), count($right));

        for ($index = 0; $index < $length; $index++) {
            $dot += $left[$index] * $right[$index];
        }

        return $dot;
    }

    private function slot(string $feature): int
    {
        return hexdec(substr(md5($feature), 0, 8)) % $this->dimensions;
    }

    private function sign(string $feature): int
    {
        return (hexdec(substr(md5($feature), 8, 2)) % 2) === 0 ? 1 : -1;
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        if ($sum <= 0.0) {
            return $vector;
        }

        $norm = sqrt($sum);

        foreach ($vector as $index => $value) {
            $vector[$index] = round($value / $norm, 6);
        }

        return $vector;
    }
}

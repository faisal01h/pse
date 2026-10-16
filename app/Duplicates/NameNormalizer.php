<?php

namespace App\Duplicates;

use Illuminate\Support\Str;

class NameNormalizer
{
    public const POLITICIAN = 'politician';

    public const PARTY = 'party';

    public const BUSINESS = 'business';

    private const HONORIFICS = [
        'dr', 'drs', 'dra', 'ir', 'prof', 'h', 'hj', 'kh', 'k', 'bapak', 'ibu', 'pak', 'bu',
        'mr', 'mrs', 'ms', 'sir', 'dato', 'datuk', 'tn', 'ny',
    ];

    private const DEGREES = [
        'sh', 'se', 'si', 'msi', 'mm', 'mh', 'ma', 'mba', 'mpd', 'spd', 'st', 'mt', 'phd', 'kom',
        'skom', 'mkom', 'sip', 'msc', 'ssos', 'sag', 'lc', 'llm', 'sosi', 'sst', 'mhum', 'shum',
    ];

    private const BUSINESS_FORMS = ['pt', 'cv', 'ud', 'pd', 'tbk', 'persero', 'perum', 'yayasan', 'ltd', 'inc', 'corp', 'co', 'llc', 'pte', 'bhd'];

    private const PARTY_WORDS = ['partai', 'party'];

    public function normalize(string $type, string $name): string
    {
        $name = Str::ascii(mb_strtolower(trim($name)));

        if ($type === self::POLITICIAN) {
            // Degrees trail after a comma ("Budi Santoso, S.H., M.Si").
            $name = explode(',', $name)[0];
        }

        $name = preg_replace('/[.\']+/', '', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9\s]+/', ' ', $name) ?? $name;
        $tokens = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $drop = match ($type) {
            self::POLITICIAN => [...self::HONORIFICS, ...self::DEGREES],
            self::BUSINESS => self::BUSINESS_FORMS,
            self::PARTY => self::PARTY_WORDS,
            default => [],
        };

        $kept = array_values(array_filter($tokens, fn ($token) => ! in_array($token, $drop, true)));

        // Never reduce a name to nothing ("Partai" alone stays "partai").
        return implode(' ', $kept !== [] ? $kept : $tokens);
    }

    /**
     * Word-order-insensitive form used for fuzzy comparison.
     */
    public function sortedKey(string $normalized): string
    {
        $tokens = explode(' ', $normalized);
        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * 0..1 similarity between two normalized names.
     */
    public function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b || $this->sortedKey($a) === $this->sortedKey($b)) {
            return 1.0;
        }

        $a = $this->sortedKey($a);
        $b = $this->sortedKey($b);
        $a = mb_substr($a, 0, 250);
        $b = mb_substr($b, 0, 250);
        $max = max(strlen($a), strlen($b));
        $edit = 1 - (levenshtein($a, $b) / $max);

        similar_text($a, $b, $percent);

        return round(max(0.0, min(1.0, ($edit * 0.6) + (($percent / 100) * 0.4))), 4);
    }
}

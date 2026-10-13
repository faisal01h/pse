<?php

namespace App\Tracking;

class SubmissionCode
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $raw = '';
        $alphabetLength = strlen(self::ALPHABET);

        for ($i = 0; $i < 16; $i++) {
            $raw .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return implode('-', str_split($raw, 4));
    }

    public static function normalize(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = str_replace([' ', '-'], '', $code);

        return $code;
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), (string) config('app.key'));
    }

    public static function matches(string $code, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }

        return hash_equals($hash, self::hash($code));
    }
}

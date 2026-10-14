<?php

namespace App\SciPol;

class MaskedId
{
    public const TYPE_POLITICIAN = 'politician';

    public const TYPE_PARTY = 'party';

    public const TYPE_ISSUE = 'issue';

    public const TYPE_BUSINESS = 'business';

    public const TYPE_AFFILIATION = 'affiliation';

    public const TYPE_RECORD = 'record';

    public const TYPE_TOPIC = 'topic';

    /**
     * @var list<string>
     */
    private const TYPES = [
        self::TYPE_POLITICIAN,
        self::TYPE_PARTY,
        self::TYPE_ISSUE,
        self::TYPE_BUSINESS,
        self::TYPE_AFFILIATION,
        self::TYPE_RECORD,
        self::TYPE_TOPIC,
    ];

    public function encode(string $type, int $id): string
    {
        if ($id < 1 || ! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Invalid SciPol id.');
        }

        $payload = $type.'.'.$id;
        $mac = substr(hash_hmac('sha256', $payload, $this->key()), 0, 10);

        return rtrim(strtr(base64_encode($payload.'.'.$mac), '+/', '-_'), '=');
    }

    public function decode(string $token, string $expectedType): ?int
    {
        $token = trim($token);
        if ($token === '' || ! in_array($expectedType, self::TYPES, true)) {
            return null;
        }

        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('.', $decoded);
        if (count($parts) !== 3) {
            return null;
        }

        [$type, $idRaw, $mac] = $parts;
        if ($type !== $expectedType || ! ctype_digit($idRaw)) {
            return null;
        }

        $id = (int) $idRaw;
        if ($id < 1) {
            return null;
        }

        $payload = $type.'.'.$id;
        $expected = substr(hash_hmac('sha256', $payload, $this->key()), 0, 10);
        if (! hash_equals($expected, $mac)) {
            return null;
        }

        return $id;
    }

    /**
     * @param  list<string|int>  $tokens
     * @return list<int>
     */
    public function decodeMany(array $tokens, string $expectedType, int $max = 3): array
    {
        $ids = [];
        foreach ($tokens as $token) {
            $id = $this->decode((string) $token, $expectedType);
            if ($id === null) {
                continue;
            }
            $ids[$id] = $id;
            if (count($ids) >= $max) {
                break;
            }
        }

        return array_values($ids);
    }

    private function key(): string
    {
        return (string) config('app.key').'|scipol-ids';
    }
}

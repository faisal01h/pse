<?php

namespace App\Plugins\NewsRadar;

use Illuminate\Support\Facades\Http;
use Throwable;

class ArticleTools
{
    private const MAX_FETCH_BYTES = 120_000;

    public function __construct(private int $timeout = 30) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'fetch_url',
                    'description' => 'Fetch HTTP text/HTML excerpt from the article URL (same host only).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => ['type' => 'string'],
                        ],
                        'required' => ['url'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'submit_news_item',
                    'description' => 'Submit extraction result or skip if not relevant to PolitiSEE.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'skip' => ['type' => 'boolean'],
                            'skip_reason' => ['type' => 'string'],
                            'politician_name' => ['type' => 'string'],
                            'position' => ['type' => 'string'],
                            'institution' => ['type' => 'string'],
                            'category' => ['type' => 'string', 'enum' => ['statement', 'allegation', 'conviction']],
                            'quote' => ['type' => 'string'],
                            'summary' => ['type' => 'string'],
                            'occurred_on' => ['type' => 'string', 'description' => 'YYYY-MM-DD if known'],
                        ],
                        'required' => ['skip'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{ok: bool, content?: string, final?: array<string, mixed>}
     */
    public function execute(string $name, array $arguments, string $articleUrl): array
    {
        return match ($name) {
            'fetch_url' => $this->fetchUrl((string) ($arguments['url'] ?? ''), $articleUrl),
            'submit_news_item' => [
                'ok' => true,
                'content' => 'Submitted.',
                'final' => $arguments,
            ],
            default => ['ok' => false, 'content' => 'Unknown tool: '.$name],
        };
    }

    /**
     * @return array{ok: bool, content: string}
     */
    private function fetchUrl(string $url, string $articleUrl): array
    {
        if (! $this->urlAllowed($url, $articleUrl)) {
            return ['ok' => false, 'content' => 'URL not allowed (SSRF protection).'];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => 'PolitiSEE-NewsRadar/1.0'])
                ->get($url);

            $body = $response->body();
            if (strlen($body) > self::MAX_FETCH_BYTES) {
                $body = substr($body, 0, self::MAX_FETCH_BYTES)."\n...[truncated]";
            }

            $text = trim(html_entity_decode(strip_tags($body)));
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

            return [
                'ok' => $response->successful(),
                'content' => 'HTTP '.$response->status()."\n".mb_substr($text, 0, 12000),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'content' => 'Fetch failed: '.$e->getMessage()];
        }
    }

    private function urlAllowed(string $url, string $articleUrl): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        $sourceHost = parse_url($articleUrl, PHP_URL_HOST);

        return is_string($sourceHost) && strtolower($sourceHost) === $host;
    }
}

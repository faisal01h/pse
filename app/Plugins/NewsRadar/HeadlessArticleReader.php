<?php

namespace App\Plugins\NewsRadar;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Renders a news article in headless Chromium (Playwright) and returns its full text.
 */
class HeadlessArticleReader
{
    public const MAX_CHARS = 120_000;

    private const TIMEOUT_SECONDS = 90;

    /**
     * @return array{title: string, text: string}
     */
    public function read(string $articleUrl): array
    {
        $data = $this->render($articleUrl, screenshot: false);

        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('Artikel kosong setelah dirender headless browser.');
        }

        if (mb_strlen($text) > self::MAX_CHARS) {
            throw new RuntimeException('Artikel terlalu panjang untuk dibaca utuh.');
        }

        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'text' => $text,
        ];
    }

    /**
     * Admin browser test: render the URL and return a screenshot plus basic extraction stats.
     *
     * @return array{final_url: string, title: string, text_length: int, excerpt: string, screenshot: string}
     */
    public function probe(string $articleUrl): array
    {
        $data = $this->render($articleUrl, screenshot: true);

        $text = trim((string) ($data['text'] ?? ''));
        $screenshot = (string) ($data['screenshot'] ?? '');

        return [
            'final_url' => (string) ($data['final_url'] ?? $articleUrl),
            'title' => trim((string) ($data['title'] ?? '')),
            'text_length' => mb_strlen($text),
            'excerpt' => mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 500),
            'screenshot' => $screenshot !== '' ? 'data:image/jpeg;base64,'.$screenshot : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function render(string $articleUrl, bool $screenshot): array
    {
        if (! $this->urlAllowed($articleUrl)) {
            throw new RuntimeException('URL artikel tidak diizinkan.');
        }

        // NEWSRADAR_NODE_BIN lets the server point at a specific Node binary.
        $node = (string) env('NEWSRADAR_NODE_BIN', 'node');
        $script = base_path('scripts/newsradar/read-article.mjs');

        $command = [$node, $script, $articleUrl];
        if ($screenshot) {
            $command[] = '--screenshot';
        }

        $process = new Process($command, base_path(), null, null, self::TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput() ?: $process->getOutput());

            if (preg_match("/Executable doesn't exist|playwright install|Cannot find package 'playwright'/i", $detail)) {
                throw new PlaywrightNotConfiguredException('Playwright atau Chromium belum terpasang.');
            }

            throw new RuntimeException('Headless browser gagal membuka halaman: '.mb_substr($detail, 0, 500));
        }

        $data = json_decode($process->getOutput(), true);
        if (! is_array($data)) {
            throw new RuntimeException('Headless browser mengembalikan output yang tidak valid.');
        }

        return $data;
    }

    private function urlAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }
}

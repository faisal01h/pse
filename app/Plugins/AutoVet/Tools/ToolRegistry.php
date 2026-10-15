<?php

namespace App\Plugins\AutoVet\Tools;

use App\Models\BusinessAffiliation;
use App\Models\PublicRecord;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class ToolRegistry
{
    private const MAX_FETCH_BYTES = 120_000;

    private const TERMINAL_ALLOWLIST = ['curl', 'rg', 'grep', 'head', 'wc', 'cat'];

    public function __construct(private int $toolTimeout = 30) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(bool $allowSpawn): array
    {
        $tools = [
            $this->functionTool('fetch_url', 'Fetch HTTP text/HTML excerpt from an allowed URL.', [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string'],
                ],
                'required' => ['url'],
            ]),
            $this->functionTool('search_duplicates', 'Search published submissions with the same source URL.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->functionTool('run_terminal', 'Run an allowlisted terminal command in the AutoVet sandbox.', [
                'type' => 'object',
                'properties' => [
                    'command' => ['type' => 'string', 'description' => 'Executable name from allowlist'],
                    'args' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['command'],
            ]),
            $this->functionTool('headless_browser', 'Open an allowed URL in a headless browser and return visible text.', [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string'],
                ],
                'required' => ['url'],
            ]),
            $this->functionTool('submit_vet_result', 'Submit the final vetting decision.', [
                'type' => 'object',
                'properties' => [
                    'check_source' => ['type' => 'boolean'],
                    'check_wording' => ['type' => 'boolean'],
                    'check_category' => ['type' => 'boolean'],
                    'check_duplicate' => ['type' => 'boolean'],
                    'check_legal_label' => ['type' => 'boolean'],
                    'review_note' => ['type' => 'string'],
                    'reliability_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    'decision' => ['type' => 'string', 'enum' => ['publish', 'reject', 'needs_human']],
                    'confidence' => ['type' => 'number'],
                    'rationale' => ['type' => 'string'],
                ],
                'required' => [
                    'check_source', 'check_wording', 'check_category', 'check_duplicate', 'check_legal_label',
                    'review_note', 'reliability_level', 'decision', 'confidence', 'rationale',
                ],
            ]),
        ];

        if ($allowSpawn) {
            $tools[] = $this->functionTool('spawn_subagent', 'Spawn a scoped subagent to investigate one question.', [
                'type' => 'object',
                'properties' => [
                    'task' => ['type' => 'string'],
                ],
                'required' => ['task'],
            ]);
        }

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{ok: bool, content: string, final?: array<string, mixed>, spawn?: string}
     */
    public function execute(string $name, array $arguments, ToolContext $context): array
    {
        return match ($name) {
            'fetch_url' => $this->fetchUrl((string) ($arguments['url'] ?? ''), $context),
            'search_duplicates' => $this->searchDuplicates($context),
            'run_terminal' => $this->runTerminal(
                (string) ($arguments['command'] ?? ''),
                is_array($arguments['args'] ?? null) ? $arguments['args'] : [],
                $context,
            ),
            'headless_browser' => $this->headlessBrowser((string) ($arguments['url'] ?? ''), $context),
            'submit_vet_result' => [
                'ok' => true,
                'content' => 'Result submitted.',
                'final' => $arguments,
            ],
            'spawn_subagent' => [
                'ok' => true,
                'content' => 'Spawning subagent.',
                'spawn' => (string) ($arguments['task'] ?? ''),
            ],
            default => ['ok' => false, 'content' => 'Unknown tool: '.$name],
        };
    }

    /**
     * @return array{ok: bool, content: string}
     */
    private function fetchUrl(string $url, ToolContext $context): array
    {
        if (! $this->urlAllowed($url, $context)) {
            return ['ok' => false, 'content' => 'URL not allowed (SSRF protection).'];
        }

        try {
            $response = Http::timeout($this->toolTimeout)
                ->withHeaders(['User-Agent' => 'PolitiSEE-AutoVet/1.0'])
                ->get($url);

            $body = $response->body();
            if (strlen($body) > self::MAX_FETCH_BYTES) {
                $body = substr($body, 0, self::MAX_FETCH_BYTES)."\n...[truncated]";
            }

            $text = trim(html_entity_decode(strip_tags($body)));
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

            return [
                'ok' => $response->successful(),
                'content' => 'HTTP '.$response->status()."\n".mb_substr($text, 0, 8000),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'content' => 'Fetch failed: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, content: string}
     */
    private function searchDuplicates(ToolContext $context): array
    {
        $url = $context->sourceUrl;
        $records = PublicRecord::query()
            ->where('source_url', $url)
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->when($context->recordId, fn ($q) => $q->where('id', '!=', $context->recordId))
            ->limit(5)
            ->get(['id', 'politician_id', 'category', 'status']);

        $affiliations = BusinessAffiliation::query()
            ->where('source_url', $url)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->when($context->affiliationId, fn ($q) => $q->where('id', '!=', $context->affiliationId))
            ->limit(5)
            ->get(['id', 'politician_id', 'business_id', 'type', 'status']);

        return [
            'ok' => true,
            'content' => json_encode([
                'published_records' => $records,
                'published_affiliations' => $affiliations,
            ], JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param  list<mixed>  $args
     * @return array{ok: bool, content: string}
     */
    private function runTerminal(string $command, array $args, ToolContext $context): array
    {
        if (! $context->terminalEnabled) {
            return ['ok' => false, 'content' => 'Terminal tool is disabled.'];
        }

        $command = basename($command);
        if (! in_array($command, self::TERMINAL_ALLOWLIST, true)) {
            return ['ok' => false, 'content' => 'Command not allowlisted.'];
        }

        $safeArgs = [];
        foreach ($args as $arg) {
            if (! is_scalar($arg)) {
                return ['ok' => false, 'content' => 'Invalid argument type.'];
            }
            $value = (string) $arg;
            if (preg_match('/[;&|`$<>\n\r]/', $value)) {
                return ['ok' => false, 'content' => 'Unsafe characters in arguments.'];
            }
            $safeArgs[] = $value;
        }

        $cwd = storage_path('app/autovet');
        if (! is_dir($cwd)) {
            mkdir($cwd, 0755, true);
        }

        $process = new Process(array_merge([$command], $safeArgs), $cwd, null, null, $this->toolTimeout);

        try {
            $process->run();
        } catch (Throwable $e) {
            return ['ok' => false, 'content' => 'Process failed: '.$e->getMessage()];
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return [
            'ok' => $process->isSuccessful(),
            'content' => mb_substr($output !== '' ? $output : '(no output)', 0, 6000),
        ];
    }

    /**
     * @return array{ok: bool, content: string}
     */
    private function headlessBrowser(string $url, ToolContext $context): array
    {
        if (! $context->browserEnabled) {
            return ['ok' => false, 'content' => 'Browser tool is disabled.'];
        }

        if (! $this->urlAllowed($url, $context)) {
            return ['ok' => false, 'content' => 'URL not allowed (SSRF protection).'];
        }

        $script = base_path('plugins/autovet-browser/browse.mjs');
        if (! is_file($script)) {
            return ['ok' => false, 'content' => 'Browser sidecar script missing.'];
        }

        $process = new Process(
            ['node', $script, $url],
            base_path('plugins/autovet-browser'),
            null,
            null,
            $this->toolTimeout,
        );

        try {
            $process->run();
        } catch (Throwable $e) {
            return ['ok' => false, 'content' => 'Browser failed: '.$e->getMessage()];
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            $output = trim($process->getErrorOutput()) ?: '(no output)';
        }

        return [
            'ok' => $process->isSuccessful(),
            'content' => mb_substr($output, 0, 8000),
        ];
    }

    private function urlAllowed(string $url, ToolContext $context): bool
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

        $allowed = array_map('strtolower', $context->allowedHosts);
        if ($allowed === []) {
            $sourceHost = parse_url($context->sourceUrl, PHP_URL_HOST);

            return is_string($sourceHost) && strtolower($sourceHost) === $host;
        }

        return in_array($host, $allowed, true);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function functionTool(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ];
    }
}

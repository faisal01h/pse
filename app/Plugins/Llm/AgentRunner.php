<?php

namespace App\Plugins\Llm;

use RuntimeException;
use Throwable;

class AgentRunner
{
    public function __construct(
        private LlmClient $client,
        private int $maxSteps = 12,
        private int $maxDepth = 1,
    ) {}

    /**
     * Run a tool-calling agent loop.
     *
     * Tool executor should return:
     * - content: string feedback
     * - ok: bool
     * - final?: array when the run is complete
     * - spawn?: string task for a nested subagent
     *
     * @param  callable(bool $allowSpawn): list<array<string, mixed>>  $definitions
     * @param  callable(string $name, array<string, mixed> $args, int $depth): array{ok: bool, content?: string, final?: array<string, mixed>, spawn?: string}  $execute
     * @param  callable(string $task): string|null  $subagentSystem
     * @return array{result: ?array<string, mixed>, steps: int, tokens: int, trace: list<array<string, mixed>>}
     */
    public function run(
        string $system,
        string $user,
        callable $definitions,
        callable $execute,
        ?callable $subagentSystem = null,
        int $depth = 0,
        ?int $budget = null,
    ): array {
        $budget ??= $this->maxSteps;
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

        $trace = [];
        $steps = 0;
        $tokens = 0;
        $allowSpawn = $depth < $this->maxDepth && $subagentSystem !== null;

        for ($i = 0; $i < $budget; $i++) {
            $steps++;
            $response = $this->client->chat($messages, $definitions($allowSpawn));
            $tokens += (int) ($response['usage']['total_tokens'] ?? 0);

            $choice = $response['choices'][0]['message'] ?? null;
            if (! is_array($choice)) {
                throw new RuntimeException('LLM returned no message.');
            }

            $toolCalls = $choice['tool_calls'] ?? [];
            if (! is_array($toolCalls) || $toolCalls === []) {
                $content = (string) ($choice['content'] ?? '');
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    return [
                        'result' => $decoded,
                        'steps' => $steps,
                        'tokens' => $tokens,
                        'trace' => $trace,
                    ];
                }

                $messages[] = $choice;
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Continue by calling your final submit tool with a structured result.',
                ];

                continue;
            }

            $messages[] = $choice;

            foreach ($toolCalls as $call) {
                $id = (string) ($call['id'] ?? uniqid('tool_', true));
                $name = (string) ($call['function']['name'] ?? '');
                $rawArgs = (string) ($call['function']['arguments'] ?? '{}');
                $args = json_decode($rawArgs, true);
                if (! is_array($args)) {
                    $args = [];
                }

                $executed = $execute($name, $args, $depth);
                $trace[] = ['tool' => $name, 'ok' => $executed['ok'] ?? false];

                if (isset($executed['final']) && is_array($executed['final'])) {
                    return [
                        'result' => $executed['final'],
                        'steps' => $steps,
                        'tokens' => $tokens,
                        'trace' => $trace,
                    ];
                }

                if (isset($executed['spawn']) && is_string($executed['spawn']) && $executed['spawn'] !== '') {
                    if (! $allowSpawn || $subagentSystem === null) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $id,
                            'content' => 'Subagent spawn refused: max depth reached.',
                        ];

                        continue;
                    }

                    try {
                        $child = $this->run(
                            $subagentSystem($executed['spawn']),
                            $executed['spawn'],
                            $definitions,
                            $execute,
                            $subagentSystem,
                            $depth + 1,
                            max(2, (int) floor($budget / 2)),
                        );
                        $steps += $child['steps'];
                        $tokens += $child['tokens'];
                        $trace = array_merge($trace, $child['trace']);
                        $summary = $child['result'] !== null
                            ? 'Subagent finished with: '.json_encode($child['result'], JSON_UNESCAPED_UNICODE)
                            : 'Subagent finished without a structured result.';
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $id,
                            'content' => $summary,
                        ];
                    } catch (Throwable $e) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $id,
                            'content' => 'Subagent failed: '.$e->getMessage(),
                        ];
                    }

                    continue;
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $id,
                    'content' => (string) ($executed['content'] ?? ''),
                ];
            }
        }

        throw new RuntimeException('Agent exceeded max steps without a final result.');
    }
}

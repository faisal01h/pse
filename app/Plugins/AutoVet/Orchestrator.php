<?php

namespace App\Plugins\AutoVet;

use App\Plugins\AutoVet\Llm\OpenAiCompatibleClient;
use App\Plugins\AutoVet\Tools\ToolContext;
use App\Plugins\AutoVet\Tools\ToolRegistry;
use RuntimeException;
use Throwable;

class Orchestrator
{
    public function __construct(
        private OpenAiCompatibleClient $client,
        private ToolRegistry $tools,
        private int $maxSteps = 12,
        private int $maxDepth = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $submission
     * @return array{result: VetResult, steps: int, tokens: int}
     */
    public function run(array $submission, ToolContext $context): array
    {
        return $this->loop($submission, $context, $this->systemPrompt(), $this->maxSteps);
    }

    /**
     * @param  array<string, mixed>  $submission
     * @return array{result: VetResult, steps: int, tokens: int}
     */
    private function loop(array $submission, ToolContext $context, string $system, int $budget, string $extraTask = ''): array
    {
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $this->userPrompt($submission, $extraTask)],
        ];

        $trace = [];
        $steps = 0;
        $tokens = 0;
        $allowSpawn = $context->depth < $this->maxDepth;

        for ($i = 0; $i < $budget; $i++) {
            $steps++;
            $response = $this->client->chat($messages, $this->tools->definitions($allowSpawn));
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
                        'result' => VetResult::fromArray($decoded, $trace),
                        'steps' => $steps,
                        'tokens' => $tokens,
                    ];
                }

                $messages[] = $choice;
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Continue by calling submit_vet_result with your final structured decision.',
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

                $executed = $this->tools->execute($name, $args, $context);
                $trace[] = ['tool' => $name, 'ok' => $executed['ok'] ?? false];

                if (isset($executed['final']) && is_array($executed['final'])) {
                    return [
                        'result' => VetResult::fromArray($executed['final'], $trace),
                        'steps' => $steps,
                        'tokens' => $tokens,
                    ];
                }

                if (isset($executed['spawn']) && is_string($executed['spawn']) && $executed['spawn'] !== '') {
                    if (! $allowSpawn) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $id,
                            'content' => 'Subagent spawn refused: max depth reached.',
                        ];

                        continue;
                    }

                    try {
                        $child = $this->loop(
                            $submission,
                            $context->withDepth($context->depth + 1),
                            $this->subagentPrompt($executed['spawn']),
                            max(2, (int) floor($budget / 2)),
                            $executed['spawn'],
                        );
                        $steps += $child['steps'];
                        $tokens += $child['tokens'];
                        $summary = 'Subagent finished. Decision hint: '.$child['result']->decision
                            .' confidence='.$child['result']->confidence
                            .' rationale='.$child['result']->rationale;
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

        throw new RuntimeException('AutoVet exceeded max steps without a final result.');
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are AutoVet for PolitiSEE, an Indonesian political archive.
Vet the submission against its source using the checklist:
1) check_source — source opens and names the person/role/event
2) check_wording — quote/summary does not invent facts beyond the source
3) check_category — category matches source (statement/allegation/conviction or ownership/endorsement)
4) check_duplicate — no published duplicate from same source URL
5) check_legal_label — allegations are not written as convictions

Reliability levels you may propose: low, medium, high (never officially_verified).
Use tools to inspect the source. Finish by calling submit_vet_result.
Prefer needs_human when uncertain. Write review_note in Indonesian, min 40 characters.
PROMPT;
    }

    private function subagentPrompt(string $task): string
    {
        return "You are an AutoVet subagent. Focus only on this task: {$task}\n"
            ."Use tools as needed, then call submit_vet_result with your best structured answer.";
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    private function userPrompt(array $submission, string $extraTask = ''): string
    {
        $payload = json_encode($submission, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $extra = $extraTask !== '' ? "\n\nSpecial focus: {$extraTask}" : '';

        return "Submission to vet:\n{$payload}{$extra}";
    }
}

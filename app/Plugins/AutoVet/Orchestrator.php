<?php

namespace App\Plugins\AutoVet;

use App\Plugins\AutoVet\Tools\ToolContext;
use App\Plugins\AutoVet\Tools\ToolRegistry;
use App\Plugins\Llm\AgentRunner;
use RuntimeException;

class Orchestrator
{
    public function __construct(
        private AgentRunner $runner,
        private ToolRegistry $tools,
    ) {}

    /**
     * @param  array<string, mixed>  $submission
     * @return array{result: VetResult, steps: int, tokens: int}
     */
    public function run(array $submission, ToolContext $context): array
    {
        $outcome = $this->runner->run(
            $this->systemPrompt(),
            $this->userPrompt($submission),
            fn (bool $allowSpawn) => $this->tools->definitions($allowSpawn),
            function (string $name, array $args, int $depth) use ($context) {
                $scoped = $context->withDepth($depth);

                return $this->tools->execute($name, $args, $scoped);
            },
            fn (string $task) => $this->subagentPrompt($task),
        );

        if (! is_array($outcome['result'])) {
            throw new RuntimeException('AutoVet finished without a structured result.');
        }

        return [
            'result' => VetResult::fromArray($outcome['result'], $outcome['trace']),
            'steps' => $outcome['steps'],
            'tokens' => $outcome['tokens'],
        ];
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
            .'Use tools as needed, then call submit_vet_result with your best structured answer.';
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

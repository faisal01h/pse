<?php

namespace App\Plugins\AutoVet;

use App\Models\BusinessAffiliation;
use App\Models\PublicRecord;
use App\Plugins\AutoVet\Llm\OpenAiCompatibleClient;
use App\Plugins\AutoVet\Models\AutoVetRun;
use App\Plugins\AutoVet\Tools\ToolContext;
use App\Plugins\AutoVet\Tools\ToolRegistry;
use App\Settings\SettingsRepository;
use RuntimeException;
use Throwable;

class AutoVetService
{
    public function __construct(
        private SettingsRepository $settings,
        private AutoVetApplier $applier,
    ) {}

    public function isConfigured(): bool
    {
        return $this->settings->getString('autovet.llm.base_url') !== ''
            && $this->settings->hasSecret('autovet.llm.api_key')
            && $this->settings->getString('autovet.llm.model') !== '';
    }

    public function runFor(PublicRecord|BusinessAffiliation $subject): AutoVetRun
    {
        $mode = $this->settings->getString('autovet.mode', 'suggest_only');
        $run = AutoVetRun::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->id,
            'status' => AutoVetRun::STATUS_RUNNING,
            'mode' => $mode,
            'started_at' => now(),
        ]);

        try {
            if (! $this->isConfigured()) {
                throw new RuntimeException('AutoVet LLM is not configured.');
            }

            $client = new OpenAiCompatibleClient(
                $this->settings->getString('autovet.llm.base_url'),
                $this->settings->getDecrypted('autovet.llm.api_key'),
                $this->settings->getString('autovet.llm.model'),
                $this->settings->getInt('autovet.timeout', 120),
            );

            $tools = new ToolRegistry(min(60, $this->settings->getInt('autovet.timeout', 120)));
            $orchestrator = new Orchestrator(
                $client,
                $tools,
                $this->settings->getInt('autovet.max_steps', 12),
            );

            $submission = $this->payload($subject);
            $host = parse_url((string) $subject->source_url, PHP_URL_HOST);
            $context = new ToolContext(
                allowedHosts: is_string($host) && $host !== '' ? [$host] : [],
                sourceUrl: (string) $subject->source_url,
                recordId: $subject instanceof PublicRecord ? $subject->id : null,
                affiliationId: $subject instanceof BusinessAffiliation ? $subject->id : null,
                terminalEnabled: $this->settings->getBool('autovet.tools.terminal_enabled', false),
                browserEnabled: $this->settings->getBool('autovet.tools.browser_enabled', false),
            );

            $outcome = $orchestrator->run($submission, $context);
            $action = $this->applier->apply($subject->fresh(), $outcome['result'], $mode);

            $run->fill([
                'status' => AutoVetRun::STATUS_COMPLETED,
                'result' => array_merge($outcome['result']->toArray(), ['action' => $action]),
                'steps' => $outcome['steps'],
                'tokens' => $outcome['tokens'],
                'finished_at' => now(),
            ]);
            $run->save();
        } catch (Throwable $e) {
            $run->fill([
                'status' => AutoVetRun::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $run->save();
        }

        return $run->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PublicRecord|BusinessAffiliation $subject): array
    {
        $politician = $subject->politician;

        if ($subject instanceof PublicRecord) {
            return [
                'kind' => 'public_record',
                'id' => $subject->id,
                'category' => $subject->category,
                'summary' => $subject->summary,
                'quote' => $subject->quote,
                'source_url' => $subject->source_url,
                'source_type' => $subject->source_type,
                'occurred_on' => optional($subject->occurred_on)->toDateString(),
                'politician' => [
                    'name' => $politician?->name,
                    'position' => $politician?->position,
                    'institution' => $politician?->institution,
                ],
            ];
        }

        return [
            'kind' => 'business_affiliation',
            'id' => $subject->id,
            'type' => $subject->type,
            'ownership_percent' => $subject->ownership_percent,
            'summary' => $subject->summary,
            'source_url' => $subject->source_url,
            'source_type' => $subject->source_type,
            'occurred_on' => optional($subject->occurred_on)->toDateString(),
            'politician' => [
                'name' => $politician?->name,
                'position' => $politician?->position,
                'institution' => $politician?->institution,
            ],
            'business' => [
                'name' => $subject->business?->name,
                'sector' => $subject->business?->sector,
            ],
        ];
    }
}

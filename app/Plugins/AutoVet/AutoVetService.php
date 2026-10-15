<?php

namespace App\Plugins\AutoVet;

use App\Models\BusinessAffiliation;
use App\Models\PublicRecord;
use App\Plugins\AutoVet\Models\AutoVetRun;
use App\Plugins\AutoVet\Tools\ToolContext;
use App\Plugins\AutoVet\Tools\ToolRegistry;
use App\Plugins\Llm\LlmService;
use App\Settings\SettingsRepository;
use MarcReichel\Laya\Laya;
use RuntimeException;
use Throwable;

class AutoVetService
{
    public const STRATEGY_LLM = 'llm';

    public const STRATEGY_LAYA = 'laya';

    public const STRATEGIES = [
        self::STRATEGY_LLM,
        self::STRATEGY_LAYA,
    ];

    public function __construct(
        private SettingsRepository $settings,
        private AutoVetApplier $applier,
        private LlmService $llm,
        private Laya $laya,
    ) {}

    public function strategy(): string
    {
        $strategy = $this->settings->getString('autovet.strategy', self::STRATEGY_LLM);

        return in_array($strategy, self::STRATEGIES, true) ? $strategy : self::STRATEGY_LLM;
    }

    public function isConfigured(): bool
    {
        return match ($this->strategy()) {
            self::STRATEGY_LAYA => $this->layaReady(),
            default => $this->llm->isConfigured(),
        };
    }

    public function layaReady(): bool
    {
        return trim((string) config('laya.url', '')) !== '';
    }

    public function runFor(PublicRecord|BusinessAffiliation $subject): AutoVetRun
    {
        $mode = $this->settings->getString('autovet.mode', 'suggest_only');
        $strategy = $this->strategy();
        $run = AutoVetRun::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->id,
            'status' => AutoVetRun::STATUS_RUNNING,
            'mode' => $mode,
            'started_at' => now(),
        ]);

        try {
            if (! $this->isConfigured()) {
                throw new RuntimeException(
                    $strategy === self::STRATEGY_LAYA
                        ? 'Laya is not configured.'
                        : 'LLM is not configured.'
                );
            }

            $outcome = $strategy === self::STRATEGY_LAYA
                ? $this->runLaya($subject)
                : $this->runLlm($subject);

            $action = $this->applier->apply($subject->fresh(), $outcome['result'], $mode);

            $run->fill([
                'status' => AutoVetRun::STATUS_COMPLETED,
                'result' => array_merge($outcome['result']->toArray(), [
                    'action' => $action,
                    'strategy' => $strategy,
                ]),
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
     * @return array{result: VetResult, steps: int, tokens: int}
     */
    private function runLlm(PublicRecord|BusinessAffiliation $subject): array
    {
        $maxSteps = $this->settings->getInt(
            'autovet.max_steps',
            $this->settings->getInt('llm.max_steps', 12)
        );
        $timeout = $this->settings->getInt(
            'autovet.timeout',
            $this->settings->getInt('llm.timeout', 120)
        );

        $tools = new ToolRegistry(min(60, $timeout));
        $orchestrator = new Orchestrator(
            $this->llm->makeAgentRunner($maxSteps),
            $tools,
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

        return $orchestrator->run($submission, $context);
    }

    /**
     * @return array{result: VetResult, steps: int, tokens: int}
     */
    private function runLaya(PublicRecord|BusinessAffiliation $subject): array
    {
        $minConfidence = $this->settings->getFloat(
            'autovet.laya_min_confidence',
            (float) config('inference.laya_min_confidence', 0.55)
        );

        $vetter = new LayaVetter($this->laya, $minConfidence);

        return $vetter->vet($this->payload($subject), $subject);
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

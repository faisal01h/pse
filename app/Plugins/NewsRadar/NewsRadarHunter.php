<?php

namespace App\Plugins\NewsRadar;

use App\Plugins\Llm\LlmService;
use App\Plugins\NewsRadar\Models\NewsRadarSighting;
use App\Settings\SettingsRepository;
use RuntimeException;
use Throwable;

class NewsRadarHunter
{
    public function __construct(
        private LlmService $llm,
        private SettingsRepository $settings,
        private SubmissionFactory $submissions,
        private ArticleTools $tools,
    ) {}

    public function process(NewsRadarSighting $sighting): NewsRadarSighting
    {
        $articleUrl = (string) ($sighting->link ?: $sighting->guid);
        if ($articleUrl === '' || ! filter_var($articleUrl, FILTER_VALIDATE_URL)) {
            $sighting->fill([
                'status' => NewsRadarSighting::STATUS_FAILED,
                'note' => 'Item tidak punya URL artikel yang valid.',
            ])->save();

            return $sighting->fresh();
        }

        try {
            if (! $this->llm->isConfigured()) {
                throw new RuntimeException('LLM is not configured.');
            }

            $maxSteps = $this->settings->getInt('llm.max_steps', 12);
            $runner = $this->llm->makeAgentRunner($maxSteps, 0);
            $tools = $this->tools;

            $outcome = $runner->run(
                $this->systemPrompt(),
                $this->userPrompt($sighting, $articleUrl),
                fn () => $tools->definitions(),
                fn (string $name, array $args) => $tools->execute($name, $args, $articleUrl),
            );

            $result = $outcome['result'] ?? null;
            if (! is_array($result)) {
                throw new RuntimeException('Agent finished without submit_news_item.');
            }

            if (! empty($result['skip'])) {
                $sighting->fill([
                    'status' => NewsRadarSighting::STATUS_SKIPPED,
                    'note' => mb_substr((string) ($result['skip_reason'] ?? 'Tidak relevan.'), 0, 2000),
                ])->save();

                return $sighting->fresh();
            }

            $record = $this->submissions->createFromAgent($result, $articleUrl);
            $sighting->fill([
                'status' => NewsRadarSighting::STATUS_SUBMITTED,
                'public_record_id' => $record->id,
                'note' => null,
            ])->save();
        } catch (Throwable $e) {
            $sighting->fill([
                'status' => NewsRadarSighting::STATUS_FAILED,
                'note' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();
        }

        return $sighting->fresh();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are NewsRadar for PolitiSEE, an Indonesian political archive.
Read the news article with fetch_url, then decide whether it concerns a public figure / politician / official worth archiving.
If relevant, extract: politician_name, optional position/institution, category (statement|allegation|conviction), quote and/or summary, optional occurred_on (YYYY-MM-DD).
If not relevant, call submit_news_item with skip=true and a short skip_reason in Indonesian.
Always finish with submit_news_item. Prefer Indonesian for quote/summary text when the source is Indonesian.
PROMPT;
    }

    private function userPrompt(NewsRadarSighting $sighting, string $articleUrl): string
    {
        return json_encode([
            'title' => $sighting->title,
            'article_url' => $articleUrl,
            'feed_url' => $sighting->feed_url,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: $articleUrl;
    }
}

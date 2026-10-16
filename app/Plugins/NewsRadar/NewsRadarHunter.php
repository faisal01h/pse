<?php

namespace App\Plugins\NewsRadar;

use App\Models\PublicRecord;
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
        private HeadlessArticleReader $reader,
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

            // Always read the full article in a headless browser. No summary or excerpt fallback.
            $article = $this->reader->read($articleUrl);

            $maxSteps = $this->settings->getInt('llm.max_steps', 12);
            $runner = $this->llm->makeAgentRunner($maxSteps, 0);
            $tools = $this->tools;

            $outcome = $runner->run(
                $this->systemPrompt(),
                $this->userPrompt($sighting, $articleUrl, $article),
                fn () => $tools->definitions(),
                fn (string $name, array $args) => $tools->execute($name, $args),
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

            $this->assertVerbatimQuote($result, $article['text']);

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

    /**
     * A public statement must carry a direct quote, and that quote must appear
     * in the rendered article exactly as written (only whitespace is normalized).
     *
     * @param  array<string, mixed>  $result
     */
    private function assertVerbatimQuote(array $result, string $articleText): void
    {
        $rawCategory = (string) ($result['category'] ?? '');
        $category = in_array($rawCategory, PublicRecord::CATEGORIES, true)
            ? $rawCategory
            : PublicRecord::CATEGORY_STATEMENT;
        $quote = trim((string) ($result['quote'] ?? ''));

        if ($category === PublicRecord::CATEGORY_STATEMENT && $quote === '') {
            throw new RuntimeException('Pernyataan publik perlu kutipan langsung dari artikel, tetapi tidak ada kutipan.');
        }

        if ($quote === '') {
            return;
        }

        if (! str_contains($this->collapseWhitespace($articleText), $this->collapseWhitespace($quote))) {
            throw new RuntimeException('Kutipan tidak cocok persis dengan teks artikel; ditolak agar kata-katanya tidak berubah.');
        }
    }

    private function collapseWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are NewsRadar for PolitiSEE, an Indonesian political archive.
The full text of the news article was rendered in a headless browser and is provided in the user message as article_text. Read all of it. Do not decide from the title or an excerpt, and do not ask for more pages.
Decide whether the article concerns a public figure / politician / official worth archiving.
If not relevant, call submit_news_item with skip=true and a short skip_reason in Indonesian.
If relevant, extract: politician_name, optional position/institution, category (statement|allegation|conviction), quote and/or summary, optional occurred_on (YYYY-MM-DD).
If the article reports a public statement by the person, you MUST put it in quote as a direct quote copied character-for-character from article_text. Keep the original language, spelling, capitalization, punctuation and wording exactly. Do not translate, paraphrase, shorten, correct, or add ellipses. If no direct quote from the person appears in article_text, do not use the statement category.
Write the summary in Indonesian.
Always finish with submit_news_item.
PROMPT;
    }

    /**
     * @param  array{title: string, text: string}  $article
     */
    private function userPrompt(NewsRadarSighting $sighting, string $articleUrl, array $article): string
    {
        return json_encode([
            'title' => $sighting->title,
            'article_url' => $articleUrl,
            'feed_url' => $sighting->feed_url,
            'article_title' => $article['title'],
            'article_text' => $article['text'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: $articleUrl;
    }
}

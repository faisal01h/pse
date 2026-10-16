<?php

namespace App\Plugins\NewsRadar;

/**
 * Tools exposed to the NewsRadar agent. The article itself is always rendered
 * by HeadlessArticleReader and passed in the prompt, so the agent has no fetch tool.
 */
class ArticleTools
{
    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
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
                            'quote' => ['type' => 'string', 'description' => 'Direct quote copied verbatim from article_text'],
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
    public function execute(string $name, array $arguments): array
    {
        return match ($name) {
            'submit_news_item' => [
                'ok' => true,
                'content' => 'Submitted.',
                'final' => $arguments,
            ],
            default => ['ok' => false, 'content' => 'Unknown tool: '.$name],
        };
    }
}

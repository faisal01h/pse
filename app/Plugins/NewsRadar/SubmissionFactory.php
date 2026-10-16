<?php

namespace App\Plugins\NewsRadar;

use App\Duplicates\DuplicateDetector;
use App\Duplicates\NameNormalizer;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\Topic;
use App\Plugins\AutoVet\Jobs\AutoVetJob;
use App\Plugins\PluginManager;
use App\Tracking\SubmissionCode;

class SubmissionFactory
{
    public function __construct(private PluginManager $plugins) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createFromAgent(array $payload, string $articleUrl): PublicRecord
    {
        $name = trim((string) ($payload['politician_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('politician_name is required.');
        }

        $institution = trim((string) ($payload['institution'] ?? '')) ?: 'Tidak diketahui';
        $position = trim((string) ($payload['position'] ?? '')) ?: 'Tokoh publik';
        $category = (string) ($payload['category'] ?? PublicRecord::CATEGORY_STATEMENT);
        if (! in_array($category, PublicRecord::CATEGORIES, true)) {
            $category = PublicRecord::CATEGORY_STATEMENT;
        }

        $politician = app(DuplicateDetector::class)->findPolitician($name, $institution);

        if (! $politician) {
            $politician = Politician::create([
                'name' => $name,
                'position' => $position,
                'institution' => $institution,
                'status' => 0,
            ]);
        }

        $quote = trim((string) ($payload['quote'] ?? '')) ?: null;
        $summary = trim((string) ($payload['summary'] ?? '')) ?: null;

        if ($category === PublicRecord::CATEGORY_STATEMENT && ($quote === null || $quote === '')) {
            $quote = $summary ?: 'Pernyataan dari sumber berita.';
        }

        if ($category !== PublicRecord::CATEGORY_STATEMENT && ($summary === null || $summary === '')) {
            $summary = $quote ?: 'Ringkasan dari sumber berita.';
        }

        $occurredOn = null;
        $rawDate = trim((string) ($payload['occurred_on'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            $occurredOn = $rawDate;
        }

        $code = SubmissionCode::generate();
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => $category,
            'quote' => $quote,
            'summary' => $summary,
            'source_url' => $articleUrl,
            'source_type' => 'Berita (NewsRadar)',
            'occurred_on' => $occurredOn,
            'status' => PublicRecord::STATUS_PENDING,
            'tracking_code_hash' => SubmissionCode::hash($code),
        ]);

        $record->addTopics([Topic::AUTO_ADDED]);

        if ($this->plugins->isEnabled('autovet')) {
            AutoVetJob::dispatch($record->getMorphClass(), $record->id);
        }

        return $record;
    }
}

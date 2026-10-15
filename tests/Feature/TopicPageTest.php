<?php

namespace Tests\Feature;

use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TopicPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_page_lists_published_records_with_that_topic(): void
    {
        $politician = $this->politician();
        $topic = Topic::create(['name' => 'Subsidi']);
        $other = Topic::create(['name' => 'Lainnya']);

        $matched = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan subsidi.',
            'source_url' => 'https://example.com/subsidi',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
            'occurred_on' => '2026-03-01',
        ]);
        $matched->assignTopics(['Subsidi']);

        $unrelated = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan lain.',
            'source_url' => 'https://example.com/lain',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
            'occurred_on' => '2026-03-02',
        ]);
        $unrelated->assignTopics(['Lainnya']);

        $pending = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Belum terbit.',
            'source_url' => 'https://example.com/pending-topic',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);
        $pending->topics()->attach($topic->id);

        $this->get(route('topics.show', $topic))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Topic')
                ->where('topic.id', $topic->id)
                ->where('topic.name', 'Subsidi')
                ->where('total', 1)
                ->has('reports', 1)
                ->where('reports.0.id', $matched->id));
    }

    public function test_topic_page_is_paginated(): void
    {
        config(['catalog.reports_per_page' => 2]);

        $politician = $this->politician();
        $topic = Topic::create(['name' => 'Energi']);

        foreach (range(1, 3) as $n) {
            $record = PublicRecord::create([
                'politician_id' => $politician->id,
                'category' => PublicRecord::CATEGORY_STATEMENT,
                'quote' => "Kutipan energi {$n}.",
                'source_url' => "https://example.com/energi-{$n}",
                'source_type' => 'Berita',
                'status' => PublicRecord::STATUS_PUBLISHED,
                'occurred_on' => sprintf('2026-04-%02d', $n),
            ]);
            $record->assignTopics(['Energi']);
        }

        $this->get(route('topics.show', $topic))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('total', 3)
                ->where('meta.last', 2)
                ->has('reports', 2));

        $this->get(route('topics.show', ['topic' => $topic, 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.current', 2)
                ->has('reports', 1));
    }

    private function politician(): Politician
    {
        return Politician::create([
            'name' => 'Tokoh Topik',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
    }
}

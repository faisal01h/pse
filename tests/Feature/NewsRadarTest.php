<?php

namespace Tests\Feature;

use App\Models\PublicRecord;
use App\Models\Topic;
use App\Plugins\NewsRadar\Jobs\NewsRadarHuntJob;
use App\Plugins\NewsRadar\Jobs\NewsRadarItemJob;
use App\Plugins\NewsRadar\Models\NewsRadarSighting;
use App\Plugins\NewsRadar\NewsRadarHunter;
use App\Plugins\NewsRadar\NewsRadarService;
use App\Plugins\NewsRadar\RssFeedReader;
use App\Plugins\NewsRadar\SubmissionFactory;
use App\Plugins\PluginManager;
use App\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SeedsAuthorization;
use Tests\TestCase;

class NewsRadarTest extends TestCase
{
    use RefreshDatabase, SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_newsradar_requires_llm(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('newsradar.enabled', true);
        $settings->set('llm.enabled', false);

        $this->assertFalse(app(PluginManager::class)->isEnabled('newsradar'));
    }

    public function test_frequency_due_logic_and_custom_minutes(): void
    {
        $settings = app(SettingsRepository::class);
        $service = app(NewsRadarService::class);

        $settings->set('newsradar.frequency', '1h');
        $settings->set('newsradar.last_run_at', '');
        $this->assertTrue($service->isDue());

        $settings->set('newsradar.last_run_at', now()->subMinutes(30)->toIso8601String());
        $this->assertFalse($service->isDue());

        $settings->set('newsradar.last_run_at', now()->subMinutes(61)->toIso8601String());
        $this->assertTrue($service->isDue());

        $settings->set('newsradar.frequency', 'custom');
        $settings->set('newsradar.custom_minutes', 10);
        $settings->set('newsradar.last_run_at', now()->subMinutes(11)->toIso8601String());
        $this->assertSame(10, $service->intervalMinutes());
        $this->assertTrue($service->isDue());
    }

    public function test_rss_reader_parses_items(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
  <channel>
    <title>Contoh</title>
    <item>
      <title>Berita Satu</title>
      <link>https://news.example.com/satu</link>
      <guid>https://news.example.com/satu</guid>
      <description>Ringkasan satu</description>
    </item>
    <item>
      <title>Berita Dua</title>
      <link>https://news.example.com/dua</link>
      <guid>guid-dua</guid>
    </item>
  </channel>
</rss>
XML;

        $items = app(RssFeedReader::class)->parse($xml, 'https://news.example.com/rss');

        $this->assertCount(2, $items);
        $this->assertSame('https://news.example.com/satu', $items[0]['link']);
        $this->assertSame('guid-dua', $items[1]['guid']);
    }

    public function test_hunt_dedupes_and_dispatches_item_jobs(): void
    {
        Queue::fake();
        $this->enableNewsRadar();

        $xml = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0"><channel>
  <item><title>A</title><link>https://news.example.com/a</link><guid>a</guid></item>
  <item><title>B</title><link>https://news.example.com/b</link><guid>b</guid></item>
</channel></rss>
XML;

        Http::fake([
            'news.example.com/rss' => Http::response($xml, 200),
        ]);

        $result = app(NewsRadarService::class)->hunt();

        $this->assertSame(2, $result['dispatched']);
        $this->assertSame(2, NewsRadarSighting::query()->count());
        Queue::assertPushed(NewsRadarItemJob::class, 2);

        // Second hunt while due should not re-dispatch duplicates.
        Queue::fake();
        app(SettingsRepository::class)->set('newsradar.last_run_at', '');
        $again = app(NewsRadarService::class)->hunt();
        $this->assertSame(0, $again['dispatched']);
        $this->assertSame(2, NewsRadarSighting::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_submission_factory_creates_pending_record(): void
    {
        $record = app(SubmissionFactory::class)->createFromAgent([
            'politician_name' => 'Sari Radar',
            'position' => 'Menteri',
            'institution' => 'Kementerian Uji',
            'category' => 'statement',
            'quote' => 'Kami akan meninjau kebijakan itu.',
            'summary' => null,
            'occurred_on' => '2026-05-01',
        ], 'https://news.example.com/story');

        $this->assertSame(PublicRecord::STATUS_PENDING, (int) $record->status);
        $this->assertSame('Berita (NewsRadar)', $record->source_type);
        $this->assertSame('Sari Radar', $record->politician->name);
        $this->assertNotNull($record->tracking_code_hash);
        $this->assertTrue($record->topics()->where('name', Topic::AUTO_ADDED)->exists());
    }

    public function test_hunter_skips_when_agent_skips(): void
    {
        $this->enableNewsRadar();

        $sighting = NewsRadarSighting::query()->create([
            'feed_url' => 'https://news.example.com/rss',
            'feed_url_hash' => hash('sha256', 'https://news.example.com/rss'),
            'guid' => 'skip-1',
            'link' => 'https://news.example.com/skip',
            'title' => 'Olahraga',
            'status' => NewsRadarSighting::STATUS_PENDING,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'submit_news_item',
                                'arguments' => json_encode([
                                    'skip' => true,
                                    'skip_reason' => 'Bukan tokoh politik.',
                                ]),
                            ],
                        ]],
                    ],
                ]],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);

        app(NewsRadarHunter::class)->process($sighting);

        $this->assertSame(NewsRadarSighting::STATUS_SKIPPED, $sighting->fresh()->status);
        $this->assertStringContainsString('Bukan tokoh', (string) $sighting->fresh()->note);
        $this->assertSame(0, PublicRecord::query()->count());
    }

    public function test_hunter_submits_pending_record_from_agent(): void
    {
        $this->enableNewsRadar();

        $sighting = NewsRadarSighting::query()->create([
            'feed_url' => 'https://news.example.com/rss',
            'feed_url_hash' => hash('sha256', 'https://news.example.com/rss'),
            'guid' => 'submit-1',
            'link' => 'https://news.example.com/submit',
            'title' => 'Pernyataan menteri',
            'status' => NewsRadarSighting::STATUS_PENDING,
        ]);

        Http::fake([
            'news.example.com/submit' => Http::response('<html><body>Menteri berkata ya.</body></html>', 200),
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [[
                                'id' => 'call_fetch',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'fetch_url',
                                    'arguments' => json_encode(['url' => 'https://news.example.com/submit']),
                                ],
                            ]],
                        ],
                    ]],
                    'usage' => ['total_tokens' => 5],
                ])
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [[
                                'id' => 'call_submit',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'submit_news_item',
                                    'arguments' => json_encode([
                                        'skip' => false,
                                        'politician_name' => 'Budi Radar',
                                        'position' => 'Menteri',
                                        'institution' => 'Kementerian A',
                                        'category' => 'statement',
                                        'quote' => 'Kami siap bertindak.',
                                    ]),
                                ],
                            ]],
                        ],
                    ]],
                    'usage' => ['total_tokens' => 8],
                ]),
        ]);

        app(NewsRadarHunter::class)->process($sighting);

        $sighting->refresh();
        $this->assertSame(NewsRadarSighting::STATUS_SUBMITTED, $sighting->status);
        $this->assertNotNull($sighting->public_record_id);
        $this->assertSame(1, PublicRecord::query()->count());
        $this->assertSame('Budi Radar', PublicRecord::query()->first()->politician->name);
    }

    public function test_settings_persist_newsradar_feeds_and_frequency(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.llm.update'), [
                'llm_enabled' => '1',
                'llm_base_url' => 'https://api.openai.com/v1',
                'llm_api_key' => 'sk-test',
                'llm_model' => 'gpt-4o-mini',
                'llm_max_steps' => '12',
                'llm_timeout' => '120',
            ])
            ->assertRedirect(route('admin.settings.llm'));

        $this->actingAs($admin)
            ->post(route('admin.settings.newsradar.update'), [
                'newsradar_enabled' => '1',
                'newsradar_feeds' => "https://news.example.com/rss\nhttps://other.example.com/atom.xml",
                'newsradar_frequency' => 'custom',
                'newsradar_custom_minutes' => '45',
            ])
            ->assertRedirect(route('admin.settings.newsradar'));

        $settings = app(SettingsRepository::class);
        $settings->forgetCache();

        $this->assertTrue($settings->getBool('newsradar.enabled'));
        $this->assertSame('custom', $settings->getString('newsradar.frequency'));
        $this->assertSame(45, $settings->getInt('newsradar.custom_minutes'));
        $this->assertSame([
            'https://news.example.com/rss',
            'https://other.example.com/atom.xml',
        ], $settings->get('newsradar.feeds'));
        $this->assertTrue(app(PluginManager::class)->isEnabled('newsradar'));
    }

    public function test_manual_hunt_dispatches_forced_hunt_job(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();
        $this->enableNewsRadar();

        $this->actingAs($admin)
            ->from(route('admin.settings.newsradar'))
            ->post(route('admin.newsradar.hunt'))
            ->assertRedirect(route('admin.settings.newsradar'))
            ->assertSessionHas('success');

        Queue::assertPushed(NewsRadarHuntJob::class, function (NewsRadarHuntJob $job) {
            return $job->force === true;
        });
        Queue::assertNotPushed(NewsRadarItemJob::class);
    }

    public function test_forced_hunt_runs_even_when_not_due(): void
    {
        Queue::fake();
        $this->enableNewsRadar();

        $settings = app(SettingsRepository::class);
        $settings->set('newsradar.last_run_at', now()->toIso8601String());
        $this->assertFalse(app(NewsRadarService::class)->isDue());

        Http::fake([
            'news.example.com/rss' => Http::response(<<<'XML'
<?xml version="1.0"?>
<rss version="2.0"><channel>
  <item>
    <title>Manual</title>
    <link>https://news.example.com/manual</link>
    <guid>manual-1</guid>
  </item>
</channel></rss>
XML, 200),
        ]);

        $result = app(NewsRadarService::class)->hunt(force: true);

        $this->assertSame(1, $result['dispatched']);
        Queue::assertPushed(NewsRadarItemJob::class, 1);
        $this->assertSame(1, NewsRadarSighting::query()->count());
    }

    public function test_manual_hunt_requires_newsradar_enabled(): void
    {
        $admin = $this->makeAdmin();
        $settings = app(SettingsRepository::class);
        $settings->set('llm.enabled', true);
        $settings->set('llm.base_url', 'https://api.openai.com/v1');
        $settings->setEncrypted('llm.api_key', 'sk-test');
        $settings->set('llm.model', 'gpt-4o-mini');
        $settings->set('newsradar.enabled', false);

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->post(route('admin.newsradar.hunt'))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('newsradar');
    }

    private function enableNewsRadar(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('llm.enabled', true);
        $settings->set('llm.base_url', 'https://api.openai.com/v1');
        $settings->setEncrypted('llm.api_key', 'sk-test');
        $settings->set('llm.model', 'gpt-4o-mini');
        $settings->set('newsradar.enabled', true);
        $settings->set('newsradar.feeds', ['https://news.example.com/rss']);
        $settings->set('newsradar.frequency', '15m');
        $settings->set('newsradar.last_run_at', '');
    }
}

<?php

namespace Tests\Feature;

use App\Models\PublicRecord;
use App\Models\Topic;
use App\Plugins\NewsRadar\HeadlessArticleReader;
use App\Plugins\NewsRadar\Jobs\NewsRadarHuntJob;
use App\Plugins\NewsRadar\Jobs\NewsRadarItemJob;
use App\Plugins\NewsRadar\Models\NewsRadarSighting;
use App\Plugins\NewsRadar\NewsRadarHunter;
use App\Plugins\NewsRadar\NewsRadarService;
use App\Plugins\NewsRadar\PlaywrightNotConfiguredException;
use App\Plugins\NewsRadar\PlaywrightSetup;
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

        $this->fakeReader(fn () => ['title' => 'Olahraga', 'text' => 'Tim nasional menang tadi malam.']);

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

        $this->fakeReader(fn () => [
            'title' => 'Pernyataan menteri',
            'text' => 'Menteri A berkata: "Kami siap bertindak." Pernyataan itu disampaikan Senin.',
        ]);
        Http::fake([
            'api.openai.com/*' => $this->submitResponse([
                'skip' => false,
                'politician_name' => 'Budi Radar',
                'position' => 'Menteri',
                'institution' => 'Kementerian A',
                'category' => 'statement',
                'quote' => 'Kami siap bertindak.',
            ]),
        ]);

        app(NewsRadarHunter::class)->process($sighting);

        $sighting->refresh();
        $this->assertSame(NewsRadarSighting::STATUS_SUBMITTED, $sighting->status);
        $this->assertNotNull($sighting->public_record_id);
        $this->assertSame(1, PublicRecord::query()->count());
        $this->assertSame('Budi Radar', PublicRecord::query()->first()->politician->name);
        $this->assertSame('Kami siap bertindak.', PublicRecord::query()->first()->quote);
    }

    public function test_hunter_rejects_quote_that_is_not_verbatim_in_article(): void
    {
        $this->enableNewsRadar();
        $sighting = $this->makeSighting('verbatim-1');

        $this->fakeReader(fn () => [
            'title' => 'Pernyataan menteri',
            'text' => 'Menteri A berkata: "Kami siap bertindak." Itu saja.',
        ]);
        Http::fake([
            'api.openai.com/*' => $this->submitResponse([
                'skip' => false,
                'politician_name' => 'Budi Radar',
                'category' => 'statement',
                'quote' => 'Kami siap untuk bertindak.',
            ]),
        ]);

        app(NewsRadarHunter::class)->process($sighting);

        $sighting->refresh();
        $this->assertSame(NewsRadarSighting::STATUS_FAILED, $sighting->status);
        $this->assertStringContainsString('tidak cocok persis', (string) $sighting->note);
        $this->assertSame(0, PublicRecord::query()->count());
    }

    public function test_hunter_rejects_statement_without_direct_quote(): void
    {
        $this->enableNewsRadar();
        $sighting = $this->makeSighting('noquote-1');

        $this->fakeReader(fn () => ['title' => 'Berita', 'text' => 'Menteri A dikabarkan akan mundur.']);
        Http::fake([
            'api.openai.com/*' => $this->submitResponse([
                'skip' => false,
                'politician_name' => 'Budi Radar',
                'category' => 'statement',
                'summary' => 'Dikabarkan akan mundur.',
            ]),
        ]);

        app(NewsRadarHunter::class)->process($sighting);

        $sighting->refresh();
        $this->assertSame(NewsRadarSighting::STATUS_FAILED, $sighting->status);
        $this->assertSame(0, PublicRecord::query()->count());
    }

    public function test_hunter_fails_instead_of_summarizing_when_headless_read_fails(): void
    {
        $this->enableNewsRadar();
        $sighting = $this->makeSighting('render-1');

        $this->fakeReader(function () {
            throw new \RuntimeException('Headless browser gagal membaca artikel: timeout');
        });
        Http::fake();

        app(NewsRadarHunter::class)->process($sighting);

        $sighting->refresh();
        $this->assertSame(NewsRadarSighting::STATUS_FAILED, $sighting->status);
        $this->assertStringContainsString('Headless browser', (string) $sighting->note);
        Http::assertNothingSent();
    }

    public function test_admin_browser_test_returns_screenshot_for_valid_url(): void
    {
        $admin = $this->makeAdmin();
        $this->app->instance(HeadlessArticleReader::class, new class extends HeadlessArticleReader {
            public function probe(string $articleUrl): array
            {
                return [
                    'final_url' => 'https://news.example.com/final',
                    'title' => 'Uji',
                    'text_length' => 42,
                    'excerpt' => 'Contoh teks',
                    'screenshot' => 'data:image/jpeg;base64,AAAA',
                ];
            }
        });

        $this->actingAs($admin)
            ->postJson(route('admin.newsradar.browser-test'), ['url' => 'https://news.example.com/story'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('title', 'Uji')
            ->assertJsonPath('text_length', 42)
            ->assertJsonPath('screenshot', 'data:image/jpeg;base64,AAAA');
    }

    public function test_admin_browser_test_reports_render_failure_and_rejects_bad_url(): void
    {
        $admin = $this->makeAdmin();
        $this->app->instance(HeadlessArticleReader::class, new class extends HeadlessArticleReader {
            public function probe(string $articleUrl): array
            {
                throw new \RuntimeException('Headless browser gagal membuka halaman: HTTP 503');
            }
        });

        $this->actingAs($admin)
            ->postJson(route('admin.newsradar.browser-test'), ['url' => 'https://news.example.com/down'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Headless browser gagal membuka halaman: HTTP 503');

        $this->actingAs($admin)
            ->postJson(route('admin.newsradar.browser-test'), ['url' => 'not a url'])
            ->assertStatus(422);
    }

    public function test_admin_browser_test_flags_missing_playwright_for_auto_setup(): void
    {
        $admin = $this->makeAdmin();
        $this->app->instance(HeadlessArticleReader::class, new class extends HeadlessArticleReader {
            public function probe(string $articleUrl): array
            {
                throw new PlaywrightNotConfiguredException('Playwright atau Chromium belum terpasang.');
            }
        });

        $this->actingAs($admin)
            ->postJson(route('admin.newsradar.browser-test'), ['url' => 'https://news.example.com/story'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('needs_setup', true);
    }

    public function test_admin_can_check_and_configure_playwright(): void
    {
        $admin = $this->makeAdmin();
        $this->app->instance(PlaywrightSetup::class, new class extends PlaywrightSetup {
            public function status(): array
            {
                return ['ready' => false, 'package' => true, 'browser' => false];
            }

            public function configure(): array
            {
                return ['ready' => true, 'package' => true, 'browser' => true];
            }
        });

        $this->actingAs($admin)
            ->getJson(route('admin.newsradar.playwright.status'))
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('browser', false);

        $this->actingAs($admin)
            ->postJson(route('admin.newsradar.playwright.configure'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ready', true);
    }

    public function test_playwright_configure_reports_install_failure(): void
    {
        $admin = $this->makeAdmin();
        $this->app->instance(PlaywrightSetup::class, new class extends PlaywrightSetup {
            public function configure(): array
            {
                throw new \RuntimeException('Gagal menjalankan npm install: ENOTFOUND registry.npmjs.org');
            }
        });

        $this->actingAs($admin)
            ->postJson(route('admin.newsradar.playwright.configure'))
            ->assertStatus(500)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Gagal menjalankan npm install: ENOTFOUND registry.npmjs.org');
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

    /**
     * @param  \Closure(string): array{title: string, text: string}  $read
     */
    private function fakeReader(\Closure $read): void
    {
        $this->app->instance(HeadlessArticleReader::class, new class($read) extends HeadlessArticleReader {
            public function __construct(private \Closure $read) {}

            public function read(string $articleUrl): array
            {
                return ($this->read)($articleUrl);
            }
        });
    }

    private function makeSighting(string $guid): NewsRadarSighting
    {
        return NewsRadarSighting::query()->create([
            'feed_url' => 'https://news.example.com/rss',
            'feed_url_hash' => hash('sha256', 'https://news.example.com/rss'),
            'guid' => $guid,
            'link' => 'https://news.example.com/'.$guid,
            'title' => 'Berita uji',
            'status' => NewsRadarSighting::STATUS_PENDING,
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function submitResponse(array $arguments)
    {
        return Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'tool_calls' => [[
                        'id' => 'call_submit',
                        'type' => 'function',
                        'function' => [
                            'name' => 'submit_news_item',
                            'arguments' => json_encode($arguments),
                        ],
                    ]],
                ],
            ]],
            'usage' => ['total_tokens' => 8],
        ], 200);
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

<?php

namespace Tests\Feature;

use App\Catalog\TemporalIntervalWriter;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Models\User;
use App\Plugins\Llm\LlmClient;
use App\Plugins\Llm\LlmService;
use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\DriftDetector;
use App\Plugins\RealPolitik\Models\SituationBrief;
use App\Plugins\RealPolitik\SignalDetector;
use App\Plugins\RealPolitik\SitrepAgent;
use App\Plugins\RealPolitik\CuiBonoAnalyzer;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Plugins\RealPolitik\RealPolitikService;
use App\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsAuthorization;
use Tests\TestCase;

class RealPolitikTest extends TestCase
{
    use RefreshDatabase, SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_plugin_disabled_by_default_and_board_hidden(): void
    {
        $this->assertFalse(app(PluginManager::class)->isEnabled('realpolitik'));
        $this->get(route('realpolitik.board'))->assertNotFound();
    }

    public function test_shock_detector_emits_signal_on_tick(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany([
            'realpolitik.enabled' => true,
            'realpolitik.sitrep_enabled' => false,
            'realpolitik.window_hours' => 24,
            'realpolitik.shock_min_count' => 3,
            'realpolitik.shock_ratio' => 2.0,
            'realpolitik.baseline_days' => 30,
            'web.realpolitik_enabled' => true,
        ]);

        $politician = Politician::create([
            'name' => 'Sari Wibowo',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 1,
        ]);

        for ($i = 0; $i < 4; $i++) {
            PublicRecord::create([
                'politician_id' => $politician->id,
                'category' => PublicRecord::CATEGORY_ALLEGATION,
                'quote' => 'Kutipan tuduhan nomor '.$i.' untuk uji lonjakan liputan politik.',
                'source_url' => 'https://example.com/shock-'.$i,
                'source_type' => 'Berita',
                'status' => PublicRecord::STATUS_PUBLISHED,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ]);
        }

        $result = app(RealPolitikService::class)->tick(force: true);
        $this->assertGreaterThanOrEqual(1, $result['signals']);
        $this->assertSame(1, PowerSignal::query()->where('politician_id', $politician->id)->count());

        $this->get(route('realpolitik.board'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RealPolitik/Board')
                ->has('signals', 1));
    }

    public function test_admin_can_update_settings_and_dispatch_tick(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.realpolitik.update'), [
                'realpolitik_enabled' => '1',
                'realpolitik_sitrep_enabled' => '0',
                'realpolitik_window_hours' => 12,
                'realpolitik_tick_minutes' => 10,
                'realpolitik_shock_ratio' => 2.5,
                'realpolitik_shock_min_count' => 3,
                'realpolitik_baseline_days' => 30,
                'realpolitik_sitrep_max_records' => 15,
            ])
            ->assertRedirect(route('admin.settings.realpolitik'));

        $this->assertTrue(app(PluginManager::class)->isEnabled('realpolitik'));

        $this->actingAs($admin)
            ->post(route('admin.realpolitik.tick'))
            ->assertRedirect();
    }

    public function test_authenticated_user_can_manage_watchlist_when_board_enabled(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany([
            'realpolitik.enabled' => true,
            'web.realpolitik_enabled' => true,
        ]);

        $user = User::factory()->create();
        $politician = Politician::create([
            'name' => 'Budi Santoso',
            'position' => 'Menteri',
            'institution' => 'Kabinet',
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('realpolitik.watchlist.store'), [
                'watchable_type' => 'politician',
                'watchable_name' => 'budi santoso',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('realpolitik_watchlists', [
            'user_id' => $user->id,
            'watchable_id' => $politician->id,
        ]);
    }

    public function test_watchlist_rejects_unknown_or_ambiguous_names(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany([
            'realpolitik.enabled' => true,
            'web.realpolitik_enabled' => true,
        ]);

        $user = User::factory()->create();
        foreach (['Sari', 'Sari'] as $name) {
            Politician::create([
                'name' => $name,
                'position' => 'Anggota',
                'institution' => 'DPR',
                'status' => 1,
            ]);
        }

        $this->actingAs($user)
            ->post(route('realpolitik.watchlist.store'), [
                'watchable_type' => 'politician',
                'watchable_name' => 'Tidak Ada',
            ])
            ->assertSessionHasErrors('watchable_name');

        $this->actingAs($user)
            ->post(route('realpolitik.watchlist.store'), [
                'watchable_type' => 'politician',
                'watchable_name' => 'Sari',
            ])
            ->assertSessionHasErrors('watchable_name');

        $this->assertDatabaseCount('realpolitik_watchlists', 0);
    }

    public function test_tick_detects_stance_drift_and_cui_bono_uses_as_of_links(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany([
            'realpolitik.enabled' => true,
            'realpolitik.sitrep_enabled' => false,
            'web.realpolitik_enabled' => true,
        ]);

        $writer = app(TemporalIntervalWriter::class);
        $a = Politician::create([
            'name' => 'Sari',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
        $b = Politician::create([
            'name' => 'Budi',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
        $issue = Issue::findOrCreateByName('Subsidi pangan');

        $writer->supersedeStance([
            'issue_id' => $issue->id,
            'politician_id' => $a->id,
            'party_id' => null,
            'position' => IssueStance::SUPPORT,
            'note' => 'Sumber menyebut dukungan subsidi pangan secara eksplisit dalam pidato resmi.',
            'source_url' => 'https://example.com/dukung',
            'source_type' => 'Pidato',
            'occurred_on' => now()->subDays(10)->toDateString(),
            'user_id' => null,
        ]);
        $writer->supersedeStance([
            'issue_id' => $issue->id,
            'politician_id' => $a->id,
            'party_id' => null,
            'position' => IssueStance::OPPOSE,
            'note' => 'Sumber kemudian menyebut penolakan terhadap perluasan subsidi pangan tersebut.',
            'source_url' => 'https://example.com/tolak',
            'source_type' => 'Wawancara',
            'occurred_on' => now()->toDateString(),
            'user_id' => null,
        ]);

        [$left, $right] = PoliticianLink::endpoints(PoliticianLink::TYPE_ALLY, $a->id, $b->id);
        $writer->openPoliticianLink([
            'politician_a_id' => $left,
            'politician_b_id' => $right,
            'type' => PoliticianLink::TYPE_ALLY,
            'summary' => 'Keduanya disebut satu koalisi dalam pemberitaan kampanye daerah itu.',
            'source_url' => 'https://example.com/sekutu',
            'source_type' => 'Berita',
            'occurred_on' => now()->subDays(30)->toDateString(),
            'user_id' => null,
        ]);

        $result = app(RealPolitikService::class)->tick(force: true);
        $this->assertGreaterThanOrEqual(1, $result['signals']);
        $this->assertTrue(
            PowerSignal::query()->where('type', PowerSignal::TYPE_DRIFT)->where('politician_id', $a->id)->exists()
        );

        $record = PublicRecord::create([
            'politician_id' => $a->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Pernyataan untuk uji peta cui bono berbasis hubungan as-of.',
            'source_url' => 'https://example.com/cuibono',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
            'occurred_on' => now()->toDateString(),
        ]);

        $map = app(CuiBonoAnalyzer::class)->forRecord($record);
        $this->assertNotEmpty($map['beneficiaries']);
        $this->assertSame('Budi', $map['beneficiaries'][0]['name']);

        $this->get(route('realpolitik.board', ['record_id' => $record->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RealPolitik/Board')
                ->where('cuiBono.as_of', $record->occurred_on->toDateString())
                ->has('cuiBono.beneficiaries', 1));
    }

    public function test_drift_dedup_is_not_suppressed_by_other_issues_or_missing_names(): void
    {
        app(SettingsRepository::class)->setMany(['realpolitik.enabled' => true]);
        $writer = app(TemporalIntervalWriter::class);
        $a = Politician::create(['name' => 'Sari', 'position' => 'Anggota', 'institution' => 'DPR', 'status' => 1]);

        foreach (['Subsidi pangan', 'Pajak karbon'] as $name) {
            $issue = Issue::findOrCreateByName($name);
            foreach ([IssueStance::SUPPORT => 10, IssueStance::OPPOSE => 0] as $position => $daysAgo) {
                $writer->supersedeStance([
                    'issue_id' => $issue->id,
                    'politician_id' => $a->id,
                    'party_id' => null,
                    'position' => $position,
                    'note' => 'Sumber menyebut posisi ini secara eksplisit dalam pernyataan resmi.',
                    'source_url' => 'https://example.com/'.$position.$issue->id,
                    'source_type' => 'Pidato',
                    'occurred_on' => now()->subDays($daysAgo)->toDateString(),
                    'user_id' => null,
                ]);
            }
        }

        $detector = app(DriftDetector::class);
        $this->assertCount(2, $detector->detect());
        $this->assertCount(0, $detector->detect());
    }

    public function test_tick_survives_a_failing_detector_and_records_last_run(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany(['realpolitik.enabled' => true, 'realpolitik.sitrep_enabled' => false]);

        $this->app->bind(SignalDetector::class, fn () => new class($settings) extends SignalDetector
        {
            public function detectShocks(?\Carbon\Carbon $now = null): array
            {
                throw new \RuntimeException('boom');
            }
        });

        $result = app(RealPolitikService::class)->tick(force: true);

        $this->assertSame(0, $result['signals']);
        $this->assertNotSame('', $settings->getString('realpolitik.last_run_at', ''));
    }

    public function test_sitrep_drops_uncited_vectors_and_hides_failure_details(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany(['realpolitik.enabled' => true, 'realpolitik.sitrep_enabled' => true]);

        $politician = Politician::create(['name' => 'Sari', 'position' => 'Anggota', 'institution' => 'DPR', 'status' => 1]);
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $ids[] = PublicRecord::create([
                'politician_id' => $politician->id,
                'category' => PublicRecord::CATEGORY_STATEMENT,
                'quote' => 'Pernyataan '.$i,
                'source_url' => 'https://example.com/'.$i,
                'source_type' => 'Berita',
                'status' => PublicRecord::STATUS_PUBLISHED,
                'occurred_on' => now()->toDateString(),
            ])->id;
        }

        $llm = $this->createMock(LlmService::class);
        $llm->method('isConfigured')->willReturn(true);
        $this->app->instance(LlmService::class, $llm);

        $payloads = [
            json_encode([
                'summary_md' => 'Ringkasan.',
                'citations' => [$ids[0]],
                'confidence' => 'medium',
                'power_vectors' => [
                    ['name' => 'Valid', 'vector' => 'gaining', 'public_record_ids' => [$ids[0], 9999]],
                    ['name' => 'Fiktif', 'vector' => 'pressured', 'public_record_ids' => [9999]],
                ],
            ]),
            null,
        ];
        $calls = 0;
        $client = new class($payloads, $calls) implements LlmClient
        {
            public function __construct(private array $payloads, private int &$calls) {}

            public function chat(array $messages, array $tools = []): array
            {
                $payload = $this->payloads[$this->calls++];
                if ($payload === null) {
                    throw new \RuntimeException('secret provider detail sk-123');
                }

                return ['choices' => [['message' => ['content' => $payload]]]];
            }
        };
        $llm->method('makeClient')->willReturn($client);

        $agent = app(SitrepAgent::class);

        $ok = $agent->generate();
        $this->assertSame(SituationBrief::STATUS_READY, $ok->status);
        $vectors = $ok->payload['power_vectors'];
        $this->assertCount(1, $vectors);
        $this->assertSame([$ids[0]], $vectors[0]['public_record_ids']);

        $failed = $agent->generate();
        $this->assertSame(SituationBrief::STATUS_FAILED, $failed->status);
        $this->assertStringNotContainsString('sk-123', $failed->summary_md);
    }

    public function test_cursor_catches_up_after_downtime_but_is_capped_and_deduped(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany(['realpolitik.enabled' => true, 'realpolitik.window_hours' => 24]);
        $service = app(RealPolitikService::class);
        $now = now();

        $settings->set('realpolitik.last_run_at', $now->copy()->subHours(2)->toIso8601String());
        $this->assertEquals($now->copy()->subHours(24)->timestamp, $service->cursor($now)->timestamp);

        $settings->set('realpolitik.last_run_at', $now->copy()->subDays(3)->toIso8601String());
        $this->assertEquals($now->copy()->subDays(3)->timestamp, $service->cursor($now)->timestamp);

        $settings->set('realpolitik.last_run_at', $now->copy()->subDays(30)->toIso8601String());
        $this->assertEquals($now->copy()->subDays(7)->timestamp, $service->cursor($now)->timestamp);
    }

    public function test_coalition_changes_are_not_limited_to_fifty_and_do_not_repeat(): void
    {
        app(SettingsRepository::class)->setMany(['realpolitik.enabled' => true]);
        $writer = app(TemporalIntervalWriter::class);
        $people = [];
        for ($i = 0; $i < 56; $i++) {
            $people[] = Politician::create(['name' => 'Tokoh '.$i, 'position' => 'Anggota', 'institution' => 'DPR', 'status' => 1]);
        }
        for ($i = 0; $i < 55; $i++) {
            [$l, $r] = PoliticianLink::endpoints(PoliticianLink::TYPE_ALLY, $people[$i]->id, $people[$i + 1]->id);
            $writer->openPoliticianLink([
                'politician_a_id' => $l,
                'politician_b_id' => $r,
                'type' => PoliticianLink::TYPE_ALLY,
                'summary' => 'Keduanya disebut bersekutu dalam pemberitaan kampanye daerah itu.',
                'source_url' => 'https://example.com/l'.$i,
                'source_type' => 'Berita',
                'occurred_on' => now()->toDateString(),
                'user_id' => null,
            ]);
        }

        $detector = app(\App\Plugins\RealPolitik\CoalitionChangeDetector::class);
        $this->assertCount(55, $detector->detect(now(), now()->subDays(2)));
        $this->assertCount(0, $detector->detect(now(), now()->subDays(2)));
    }

    public function test_board_flags_and_filters_signals_by_watchlist(): void
    {
        app(SettingsRepository::class)->setMany(['realpolitik.enabled' => true, 'web.realpolitik_enabled' => true]);
        $user = User::factory()->create();
        $watched = Politician::create(['name' => 'Sari', 'position' => 'Anggota', 'institution' => 'DPR', 'status' => 1]);
        $other = Politician::create(['name' => 'Budi', 'position' => 'Anggota', 'institution' => 'DPR', 'status' => 1]);
        foreach ([$watched, $other] as $p) {
            PowerSignal::create([
                'type' => PowerSignal::TYPE_SHOCK,
                'severity' => PowerSignal::SEVERITY_MEDIUM,
                'politician_id' => $p->id,
                'title' => 'Sinyal '.$p->name,
            ]);
        }
        \App\Plugins\RealPolitik\Models\WatchlistItem::create([
            'user_id' => $user->id,
            'watchable_type' => Politician::class,
            'watchable_id' => $watched->id,
        ]);

        $this->actingAs($user)->get(route('realpolitik.board'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('signals', 2)
                ->where('signals.0.watched', fn ($v) => is_bool($v))
                ->where('onlyWatched', false));

        $this->actingAs($user)->get(route('realpolitik.board', ['watching' => 1]))
            ->assertInertia(fn ($page) => $page
                ->has('signals', 1)
                ->where('signals.0.title', 'Sinyal Sari')
                ->where('signals.0.watched', true)
                ->where('onlyWatched', true));
    }
}

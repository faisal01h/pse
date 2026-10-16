<?php

namespace Tests\Feature;

use App\Catalog\TemporalIntervalWriter;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Models\User;
use App\Plugins\PluginManager;
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
                'watchable_id' => $politician->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('realpolitik_watchlists', [
            'user_id' => $user->id,
            'watchable_id' => $politician->id,
        ]);
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
}

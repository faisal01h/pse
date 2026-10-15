<?php

namespace Tests\Feature;

use App\Catalog\ReliabilityLevel;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\Topic;
use App\Plugins\AutoVet\AutoVetApplier;
use App\Plugins\AutoVet\AutoVetService;
use App\Plugins\AutoVet\Jobs\AutoVetJob;
use App\Plugins\AutoVet\Models\AutoVetRun;
use App\Plugins\AutoVet\VetResult;
use App\Plugins\Llm\LlmService;
use App\Plugins\PluginManager;
use App\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SeedsAuthorization;
use Tests\TestCase;

class SettingsAndAutoVetTest extends TestCase
{
    use RefreshDatabase, SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_update_llm_and_autovet_settings(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertRedirect(route('admin.settings.web'));

        $this->actingAs($admin)
            ->get(route('admin.settings.llm'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings/Llm'));

        $this->actingAs($admin)
            ->post(route('admin.settings.llm.update'), [
                'llm_enabled' => '1',
                'llm_base_url' => 'https://api.openai.com/v1',
                'llm_api_key' => 'sk-test-secret',
                'llm_model' => 'gpt-4o-mini',
                'llm_max_steps' => '10',
                'llm_timeout' => '90',
            ])
            ->assertRedirect(route('admin.settings.llm'));

        $this->actingAs($admin)
            ->post(route('admin.settings.autovet.update'), [
                'autovet_enabled' => '1',
                'autovet_strategy' => 'llm',
                'autovet_mode' => 'hybrid',
                'autovet_hybrid_min_confidence' => '0.75',
                'autovet_tools_terminal_enabled' => '0',
                'autovet_tools_browser_enabled' => '0',
            ])
            ->assertRedirect(route('admin.settings.autovet'));

        $settings = app(SettingsRepository::class);
        $settings->forgetCache();

        $this->assertTrue($settings->getBool('llm.enabled'));
        $this->assertTrue($settings->getBool('autovet.enabled'));
        $this->assertSame('llm', $settings->getString('autovet.strategy'));
        $this->assertSame('hybrid', $settings->getString('autovet.mode'));
        $this->assertSame('sk-test-secret', $settings->getDecrypted('llm.api_key'));
        $this->assertNotSame('sk-test-secret', $settings->get('llm.api_key'));
        $this->assertTrue(app(PluginManager::class)->isEnabled('llm'));
        $this->assertTrue(app(PluginManager::class)->isEnabled('autovet'));
    }

    public function test_autovet_llm_strategy_requires_llm(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.enabled', true);
        $settings->set('autovet.strategy', 'llm');
        $settings->set('llm.enabled', false);

        $this->assertFalse(app(PluginManager::class)->isEnabled('autovet'));
    }

    public function test_autovet_laya_strategy_works_without_llm(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.enabled', true);
        $settings->set('autovet.strategy', 'laya');
        $settings->set('llm.enabled', false);
        config(['laya.url' => 'http://laya.test']);

        $this->assertTrue(app(PluginManager::class)->isEnabled('autovet'));
        $this->assertSame('laya', app(\App\Plugins\AutoVet\AutoVetService::class)->strategy());
    }

    public function test_models_proxy_requires_settings_and_returns_ids(): void
    {
        $admin = $this->makeAdmin();
        $settings = app(SettingsRepository::class);
        $settings->set('llm.enabled', true);
        $settings->set('llm.base_url', 'https://api.openai.com/v1');
        $settings->setEncrypted('llm.api_key', 'sk-test');

        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-4o-mini'],
                    ['id' => 'gpt-4o'],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.llm.models'))
            ->assertOk()
            ->assertJson([
                'models' => ['gpt-4o', 'gpt-4o-mini'],
            ]);
    }

    public function test_legacy_autovet_llm_settings_are_migrated(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.llm.base_url', 'https://api.openai.com/v1');
        $settings->setEncrypted('autovet.llm.api_key', 'sk-legacy');
        $settings->set('autovet.llm.model', 'gpt-4o-mini');

        app(LlmService::class)->migrateLegacySettings();
        $settings->forgetCache();

        $this->assertSame('https://api.openai.com/v1', $settings->getString('llm.base_url'));
        $this->assertSame('sk-legacy', $settings->getDecrypted('llm.api_key'));
        $this->assertSame('gpt-4o-mini', $settings->getString('llm.model'));
        $this->assertTrue($settings->getBool('llm.enabled'));
    }

    public function test_reliability_clamp_rejects_officially_verified_for_autovet(): void
    {
        $this->assertSame(
            ReliabilityLevel::HIGH,
            ReliabilityLevel::clampForAuto(ReliabilityLevel::OFFICIALLY_VERIFIED)
        );
        $this->assertSame(ReliabilityLevel::MEDIUM, ReliabilityLevel::clampForAuto('medium'));
        $this->assertNull(ReliabilityLevel::clampForAuto('nope'));
    }

    public function test_suggest_only_writes_draft_without_publishing(): void
    {
        $this->seedAuthorization();
        $record = $this->pendingRecord();
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.mode', 'suggest_only');

        $result = VetResult::fromArray([
            'check_source' => true,
            'check_wording' => true,
            'check_category' => true,
            'check_duplicate' => true,
            'check_legal_label' => true,
            'review_note' => 'Sumber terbuka dan kutipan sesuai dengan pernyataan di berita.',
            'reliability_level' => ReliabilityLevel::OFFICIALLY_VERIFIED,
            'decision' => 'publish',
            'confidence' => 0.95,
            'rationale' => 'Cocok.',
        ]);

        $action = app(AutoVetApplier::class)->apply($record, $result, 'suggest_only');

        $this->assertSame('draft', $action);
        $record->refresh();
        $this->assertSame(PublicRecord::STATUS_PENDING, (int) $record->status);
        $this->assertSame(ReliabilityLevel::HIGH, $record->autovet_draft['reliability_level']);
        $this->assertTrue($record->topics()->where('name', Topic::AI_CHECKED)->exists());
    }

    public function test_auto_decide_publishes_and_clamps_reliability(): void
    {
        $this->seedAuthorization();
        $record = $this->pendingRecord();

        $result = VetResult::fromArray([
            'check_source' => true,
            'check_wording' => true,
            'check_category' => true,
            'check_duplicate' => true,
            'check_legal_label' => true,
            'review_note' => 'Sumber terbuka dan kutipan sesuai dengan pernyataan di berita.',
            'reliability_level' => ReliabilityLevel::OFFICIALLY_VERIFIED,
            'decision' => 'publish',
            'confidence' => 0.99,
            'rationale' => 'Cocok.',
        ]);

        $action = app(AutoVetApplier::class)->apply($record, $result, 'auto_decide');

        $this->assertSame('applied', $action);
        $record->refresh();
        $this->assertSame(PublicRecord::STATUS_PUBLISHED, (int) $record->status);
        $this->assertSame(ReliabilityLevel::HIGH, $record->reliability_level);
        $this->assertNull($record->autovet_draft);
        $this->assertSame(1, (int) $record->politician->fresh()->status);
        $this->assertTrue($record->topics()->where('name', Topic::AI_CHECKED)->exists());
    }

    public function test_hybrid_below_threshold_stays_draft(): void
    {
        $this->seedAuthorization();
        $record = $this->pendingRecord();
        app(SettingsRepository::class)->set('autovet.hybrid_min_confidence', 0.9);

        $result = VetResult::fromArray([
            'check_source' => true,
            'check_wording' => true,
            'check_category' => true,
            'check_duplicate' => true,
            'check_legal_label' => true,
            'review_note' => 'Sumber terbuka dan kutipan sesuai dengan pernyataan di berita.',
            'reliability_level' => 'medium',
            'decision' => 'publish',
            'confidence' => 0.5,
            'rationale' => 'Ragu.',
        ]);

        $action = app(AutoVetApplier::class)->apply($record, $result, 'hybrid');

        $this->assertSame('draft', $action);
        $this->assertSame(PublicRecord::STATUS_PENDING, (int) $record->fresh()->status);
    }

    public function test_submission_dispatches_autovet_job_when_enabled(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();
        $this->enableLlmAndAutoVet();

        $politician = Politician::create([
            'name' => 'Antri Auto',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 0,
        ]);
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan.',
            'source_url' => 'https://example.com/a',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('reports.autovet', $record))
            ->assertRedirect();

        Queue::assertPushed(AutoVetJob::class, 1);
    }

    public function test_fetch_tool_blocks_private_hosts(): void
    {
        $registry = new \App\Plugins\AutoVet\Tools\ToolRegistry;
        $context = new \App\Plugins\AutoVet\Tools\ToolContext(
            allowedHosts: ['example.com'],
            sourceUrl: 'https://example.com/story',
            recordId: 1,
            affiliationId: null,
            terminalEnabled: false,
            browserEnabled: false,
        );

        $result = $registry->execute('fetch_url', ['url' => 'http://127.0.0.1/secret'], $context);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not allowed', $result['content']);
    }

    public function test_terminal_tool_blocks_disallowed_commands(): void
    {
        $registry = new \App\Plugins\AutoVet\Tools\ToolRegistry;
        $context = new \App\Plugins\AutoVet\Tools\ToolContext(
            allowedHosts: ['example.com'],
            sourceUrl: 'https://example.com/story',
            recordId: null,
            affiliationId: null,
            terminalEnabled: true,
            browserEnabled: false,
        );

        $result = $registry->execute('run_terminal', ['command' => 'rm', 'args' => ['-rf', '/']], $context);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('allowlisted', $result['content']);
    }

    public function test_service_marks_run_failed_when_llm_not_configured(): void
    {
        $this->seedAuthorization();
        $record = $this->pendingRecord();
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.enabled', true);
        $settings->set('autovet.strategy', 'llm');

        $run = app(AutoVetService::class)->runFor($record);

        $this->assertSame(AutoVetRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('not configured', (string) $run->error);
    }

    public function test_laya_strategy_completes_vet_run(): void
    {
        $this->seedAuthorization();
        $record = $this->pendingRecord();
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.enabled', true);
        $settings->set('autovet.strategy', 'laya');
        $settings->set('autovet.mode', 'suggest_only');
        config(['laya.url' => 'http://laya.test']);

        \MarcReichel\Laya\Laya::fake([
            'check_source' => true,
            'check_wording' => true,
            'check_category' => true,
            'check_legal_label' => true,
            'decision' => 'publish',
            'reliability' => 'medium',
            'confidence' => 3,
        ]);

        Http::fake([
            'example.com/autovet' => Http::response('<html><body>Tokoh Auto berkata kutipan untuk AutoVet.</body></html>', 200),
        ]);

        $run = app(AutoVetService::class)->runFor($record);

        $this->assertSame(AutoVetRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('laya', $run->result['strategy'] ?? null);
        $this->assertSame('draft', $run->result['action'] ?? null);
        $this->assertTrue((bool) ($run->result['check_source'] ?? false));
        $this->assertTrue($record->fresh()->topics()->where('name', Topic::AI_CHECKED)->exists());
    }

    private function enableLlmAndAutoVet(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('llm.enabled', true);
        $settings->set('llm.base_url', 'https://api.openai.com/v1');
        $settings->setEncrypted('llm.api_key', 'sk-test');
        $settings->set('llm.model', 'gpt-4o-mini');
        $settings->set('autovet.enabled', true);
        $settings->set('autovet.strategy', 'llm');
    }

    private function pendingRecord(): PublicRecord
    {
        $politician = Politician::create([
            'name' => 'Tokoh Auto',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 0,
        ]);

        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan untuk AutoVet.',
            'source_url' => 'https://example.com/autovet',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);
    }
}

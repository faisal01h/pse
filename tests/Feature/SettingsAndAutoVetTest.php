<?php

namespace Tests\Feature;

use App\Catalog\ReliabilityLevel;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Plugins\AutoVet\AutoVetApplier;
use App\Plugins\AutoVet\AutoVetService;
use App\Plugins\AutoVet\Jobs\AutoVetJob;
use App\Plugins\AutoVet\Models\AutoVetRun;
use App\Plugins\AutoVet\VetResult;
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

    public function test_admin_can_update_autovet_settings_and_api_key_is_encrypted(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings'));

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'autovet_enabled' => '1',
                'autovet_mode' => 'hybrid',
                'autovet_hybrid_min_confidence' => '0.75',
                'autovet_llm_base_url' => 'https://api.openai.com/v1',
                'autovet_llm_api_key' => 'sk-test-secret',
                'autovet_llm_model' => 'gpt-4o-mini',
                'autovet_tools_terminal_enabled' => '0',
                'autovet_tools_browser_enabled' => '0',
                'autovet_max_steps' => '10',
                'autovet_timeout' => '90',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $settings = app(SettingsRepository::class);
        $settings->forgetCache();

        $this->assertTrue($settings->getBool('autovet.enabled'));
        $this->assertSame('hybrid', $settings->getString('autovet.mode'));
        $this->assertSame('sk-test-secret', $settings->getDecrypted('autovet.llm.api_key'));
        $this->assertNotSame('sk-test-secret', $settings->get('autovet.llm.api_key'));
        $this->assertTrue(app(PluginManager::class)->isEnabled('autovet'));
    }

    public function test_models_proxy_requires_settings_and_returns_ids(): void
    {
        $admin = $this->makeAdmin();
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.llm.base_url', 'https://api.openai.com/v1');
        $settings->setEncrypted('autovet.llm.api_key', 'sk-test');

        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-4o-mini'],
                    ['id' => 'gpt-4o'],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.autovet.models'))
            ->assertOk()
            ->assertJson([
                'models' => ['gpt-4o', 'gpt-4o-mini'],
            ]);
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
        $settings = app(SettingsRepository::class);
        $settings->set('autovet.enabled', true);
        $settings->set('autovet.llm.base_url', 'https://api.openai.com/v1');
        $settings->setEncrypted('autovet.llm.api_key', 'sk-test');
        $settings->set('autovet.llm.model', 'gpt-4o-mini');

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
        app(SettingsRepository::class)->set('autovet.enabled', true);

        $run = app(AutoVetService::class)->runFor($record);

        $this->assertSame(AutoVetRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('not configured', (string) $run->error);
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

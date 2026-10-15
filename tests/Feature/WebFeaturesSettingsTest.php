<?php

namespace Tests\Feature;

use App\Settings\SettingsRepository;
use App\Web\WebFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesTurnstile;
use Tests\Support\SeedsAuthorization;
use Tests\TestCase;

class WebFeaturesSettingsTest extends TestCase
{
    use FakesTurnstile, RefreshDatabase, SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_update_web_features(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.web'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings/Web'));

        $this->actingAs($admin)
            ->post(route('admin.settings.web.update'), [
                'web_submissions_enabled' => '0',
                'web_tracking_enabled' => '1',
                'web_disputes_enabled' => '0',
                'web_scipol_enabled' => '0',
                'web_turnstile_enabled' => '0',
                'web_site_notice' => 'Pemeliharaan terjadwal.',
            ])
            ->assertRedirect(route('admin.settings.web'));

        $settings = app(SettingsRepository::class);
        $settings->forgetCache();
        $features = app(WebFeatures::class);

        $this->assertFalse($features->submissionsEnabled());
        $this->assertTrue($features->trackingEnabled());
        $this->assertFalse($features->disputesEnabled());
        $this->assertFalse($features->scipolEnabled());
        $this->assertFalse($features->turnstileEnabled());
        $this->assertSame('Pemeliharaan terjadwal.', $features->siteNotice());
    }

    public function test_disabled_submissions_return_not_found(): void
    {
        app(SettingsRepository::class)->set('web.submissions_enabled', false);

        $this->get(route('reports.create'))->assertNotFound();
        $this->post(route('newReport'), [])->assertNotFound();
    }

    public function test_disabled_disputes_and_scipol_return_not_found(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('web.disputes_enabled', false);
        $settings->set('web.scipol_enabled', false);

        $this->get(route('dispute.create'))->assertNotFound();
        $this->get(route('scipol.hub'))->assertNotFound();
    }

    public function test_disabled_tracking_return_not_found(): void
    {
        app(SettingsRepository::class)->set('web.tracking_enabled', false);

        $this->get(route('reports.track'))->assertNotFound();
    }

    public function test_turnstile_skipped_when_disabled(): void
    {
        app(SettingsRepository::class)->set('web.turnstile_enabled', false);

        $this->post('/reports', [
            'name' => 'Tanpa Turnstile',
            'position' => 'Anggota',
            'department' => 'DPR',
            'party_id' => '',
            'category' => 'statement',
            'quote' => 'Kutipan tanpa verifikasi turnstile.',
            'evtype' => 'berita',
            'link' => 'https://example.com/no-turnstile',
        ])->assertRedirect();

        $this->assertDatabaseCount('public_records', 1);
    }

    public function test_home_shares_features_and_notice(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('web.submissions_enabled', false);
        $settings->set('web.site_notice', 'Pengumuman uji.');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('features.submissions', false)
                ->where('site_notice', 'Pengumuman uji.')
                ->where('turnstile.enabled', true));
    }
}

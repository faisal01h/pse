<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\SearchDocument;
use App\Models\User;
use App\Search\Embedder;
use App\Search\HashingEmbedder;
use App\Tracking\SubmissionCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesTurnstile;
use Tests\TestCase;

class BusinessAffiliationTest extends TestCase
{
    use FakesTurnstile, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->app->forgetInstance(Embedder::class);
        $this->app->singleton(Embedder::class, fn () => new HashingEmbedder(256));
    }

    public function test_public_record_submission_still_works_after_kind_fork(): void
    {
        $this->fakeTurnstile('report');

        $response = $this->post('/reports', $this->withTurnstileToken([
            'name' => 'Catatan Uji',
            'position' => 'Anggota',
            'department' => 'DPR',
            'party_id' => '',
            'category' => 'statement',
            'quote' => 'Kutipan regresi catatan publik.',
            'evtype' => 'berita',
            'link' => 'https://example.com/catatan',
        ]));

        $response->assertRedirect(route('reports.receipt'));
        $this->assertDatabaseCount('public_records', 1);
        $this->assertDatabaseCount('business_affiliations', 0);
    }

    public function test_affiliation_submission_tracks_and_stays_hidden_until_published(): void
    {
        $this->fakeTurnstile('affiliation');

        $response = $this->post(route('reports.affiliations.store'), $this->affiliationPayload([
            'ownership_unknown' => '1',
        ]));

        $response->assertRedirect(route('reports.receipt'));
        $code = session('submission_code');
        $this->assertNotEmpty($code);

        $affiliation = BusinessAffiliation::query()->first();
        $this->assertNotNull($affiliation);
        $this->assertSame(BusinessAffiliation::STATUS_PENDING, (int) $affiliation->status);
        $this->assertSame(BusinessAffiliation::TYPE_OWNERSHIP, $affiliation->type);
        $this->assertNull($affiliation->ownership_percent);
        $this->assertSame(SubmissionCode::hash($code), $affiliation->tracking_code_hash);

        $this->get('/businesses')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Businesses')
            ->has('businesses', 0));

        $this->post('/reports/track', [
            'kind' => 'submission',
            'tracking_code' => $code,
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lacak')
                ->where('kind', 'submission')
                ->where('progress.status', 'menunggu')
                ->where('progress.politician', 'Tokoh Bisnis'));
    }

    public function test_publish_shows_business_catalog_profile_section_and_search(): void
    {
        $user = User::factory()->create();
        $affiliation = $this->pendingAffiliation([
            'type' => BusinessAffiliation::TYPE_OWNERSHIP,
            'ownership_percent' => 40,
        ]);

        $this->actingAs($user)
            ->post(route('affiliations.decide', $affiliation), $this->decisionPayload($affiliation, [
                'ownership_percent' => 40,
                'ownership_unknown' => '0',
            ]))
            ->assertRedirect(route('dashboard'));

        $affiliation->refresh();
        $this->assertSame(BusinessAffiliation::STATUS_PUBLISHED, (int) $affiliation->status);
        $this->assertSame(Business::STATUS_PUBLISHED, (int) $affiliation->business->fresh()->status);
        $this->assertSame(1, (int) $affiliation->politician->fresh()->status);

        $this->get('/businesses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Businesses')
                ->has('businesses', 1)
                ->where('businesses.0.name', 'PT Contoh'));

        $this->get(route('businesses.show', $affiliation->business_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Business')
                ->where('business.name', 'PT Contoh')
                ->has('business.affiliations', 1)
                ->where('business.affiliations.0.label', 'Kepemilikan 40%'));

        $this->get(route('politicians.show', $affiliation->politician_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Politician')
                ->has('politician.business_affiliations', 1)
                ->where('politician.business_affiliations.0.business.name', 'PT Contoh'));

        $this->assertDatabaseHas('search_documents', [
            'type' => SearchDocument::TYPE_BUSINESS,
            'source_id' => $affiliation->business_id,
            'title' => 'PT Contoh',
        ]);

        $this->getJson('/search?q=PT%20Contoh')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'business',
                'title' => 'PT Contoh',
                'href' => '/businesses/'.$affiliation->business_id,
            ]);
    }

    public function test_unknown_and_full_ownership_labels(): void
    {
        $user = User::factory()->create();
        $unknown = $this->pendingAffiliation([
            'ownership_percent' => null,
            'business_name' => 'Toko A',
        ]);
        $full = $this->pendingAffiliation([
            'ownership_percent' => 100,
            'name' => 'Tokoh Lain',
            'department' => 'Kementerian',
            'business_name' => 'Toko B',
            'link' => 'https://example.com/full',
        ]);

        $this->actingAs($user)
            ->post(route('affiliations.decide', $unknown), $this->decisionPayload($unknown, [
                'ownership_unknown' => '1',
                'ownership_percent' => '',
                'business_id' => 'other',
                'business_name' => 'Toko A',
            ]))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->post(route('affiliations.decide', $full), $this->decisionPayload($full, [
                'ownership_percent' => 100,
                'ownership_unknown' => '0',
                'name' => 'Tokoh Lain',
                'department' => 'Kementerian',
                'business_id' => 'other',
                'business_name' => 'Toko B',
                'source_url' => 'https://example.com/full',
            ]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame('Kepemilikan (jumlah tidak diketahui)', $unknown->fresh()->typeLabel());
        $this->assertSame('Kepemilikan penuh (100%)', $full->fresh()->typeLabel());
    }

    public function test_reject_keeps_business_off_catalog(): void
    {
        $user = User::factory()->create();
        $affiliation = $this->pendingAffiliation();

        $this->actingAs($user)
            ->post(route('affiliations.decide', $affiliation), $this->decisionPayload($affiliation, [
                'decision' => 'reject',
                'ownership_unknown' => '1',
            ]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(BusinessAffiliation::STATUS_REJECTED, (int) $affiliation->fresh()->status);
        $this->get('/businesses')->assertInertia(fn ($page) => $page->has('businesses', 0));
        $this->get(route('businesses.show', $affiliation->business_id))->assertNotFound();
    }

    public function test_duplicate_published_affiliation_is_blocked(): void
    {
        $user = User::factory()->create();
        $first = $this->pendingAffiliation(['ownership_percent' => 25]);

        $this->actingAs($user)
            ->post(route('affiliations.decide', $first), $this->decisionPayload($first, [
                'ownership_percent' => 25,
                'ownership_unknown' => '0',
            ]))
            ->assertRedirect(route('dashboard'));

        $second = $this->pendingAffiliation([
            'link' => 'https://example.com/dup',
            'ownership_percent' => 30,
        ]);
        $second->update([
            'politician_id' => $first->politician_id,
            'business_id' => $first->business_id,
        ]);

        $this->actingAs($user)
            ->post(route('affiliations.decide', $second), $this->decisionPayload($second, [
                'ownership_percent' => 30,
                'ownership_unknown' => '0',
                'business_id' => (string) $first->business_id,
                'source_url' => 'https://example.com/dup',
            ]))
            ->assertSessionHasErrors('check_duplicate');

        $this->assertSame(BusinessAffiliation::STATUS_PENDING, (int) $second->fresh()->status);
    }

    public function test_admin_can_add_published_affiliation_from_politician_form(): void
    {
        $user = User::factory()->create();
        $politician = Politician::create([
            'name' => 'Admin Tokoh',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
        PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Profil sudah terbit.',
            'source_url' => 'https://example.com/profil',
            'source_type' => 'berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);

        $this->actingAs($user)
            ->post(route('admin.politicians.affiliations.store', $politician), [
                'business_id' => 'other',
                'business_name' => 'Merek Endorsed',
                'type' => BusinessAffiliation::TYPE_ENDORSEMENT,
                'summary' => str_repeat('Ringkasan endorsemen dari sumber publik. ', 3),
                'source_url' => 'https://example.com/endorse',
                'source_type' => 'Iklan',
            ])
            ->assertRedirect(route('admin.politicians.edit', $politician));

        $this->assertDatabaseHas('business_affiliations', [
            'politician_id' => $politician->id,
            'type' => BusinessAffiliation::TYPE_ENDORSEMENT,
            'status' => BusinessAffiliation::STATUS_PUBLISHED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function affiliationPayload(array $overrides = []): array
    {
        return $this->withTurnstileToken(array_merge([
            'name' => 'Tokoh Bisnis',
            'position' => 'Anggota',
            'department' => 'DPR',
            'party_id' => '',
            'business_id' => 'other',
            'business_name' => 'PT Contoh',
            'type' => BusinessAffiliation::TYPE_OWNERSHIP,
            'ownership_percent' => '',
            'ownership_unknown' => '1',
            'summary' => 'Sumber menyebut tokoh memiliki saham di perusahaan ini.',
            'evtype' => 'berita',
            'link' => 'https://example.com/afiliasi',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pendingAffiliation(array $overrides = []): BusinessAffiliation
    {
        $payload = $this->affiliationPayload($overrides);
        unset($payload['cf-turnstile-response'], $payload['evtype'], $payload['link'], $payload['ownership_unknown']);

        $name = $overrides['name'] ?? 'Tokoh Bisnis';
        $department = $overrides['department'] ?? 'DPR';
        $businessName = $overrides['business_name'] ?? 'PT Contoh';
        $link = $overrides['link'] ?? 'https://example.com/afiliasi-'.uniqid();

        $politician = Politician::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->whereRaw('LOWER(institution) = ?', [mb_strtolower($department)])
            ->first();

        if (! $politician) {
            $politician = Politician::create([
                'name' => $name,
                'position' => $overrides['position'] ?? 'Anggota',
                'institution' => $department,
                'status' => 0,
            ]);
        }

        $business = Business::findOrCreateByName($businessName, Business::STATUS_DRAFT);

        return BusinessAffiliation::create([
            'politician_id' => $politician->id,
            'business_id' => $business->id,
            'type' => $overrides['type'] ?? BusinessAffiliation::TYPE_OWNERSHIP,
            'ownership_percent' => array_key_exists('ownership_percent', $overrides)
                ? $overrides['ownership_percent']
                : null,
            'summary' => $overrides['summary'] ?? 'Sumber menyebut tokoh memiliki saham di perusahaan ini.',
            'source_url' => $link,
            'source_type' => $overrides['evtype'] ?? 'berita',
            'status' => BusinessAffiliation::STATUS_PENDING,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function decisionPayload(BusinessAffiliation $affiliation, array $overrides = []): array
    {
        $affiliation->loadMissing(['politician', 'business']);

        return array_merge([
            'decision' => 'publish',
            'name' => $affiliation->politician->name,
            'position' => $affiliation->politician->position,
            'department' => $affiliation->politician->institution,
            'party_id' => '',
            'business_id' => (string) $affiliation->business_id,
            'type' => $affiliation->type,
            'ownership_percent' => $affiliation->ownership_percent ?? '',
            'ownership_unknown' => $affiliation->ownership_percent === null ? '1' : '0',
            'summary' => $affiliation->summary,
            'source_type' => $affiliation->source_type,
            'source_url' => $affiliation->source_url,
            'review_note' => str_repeat('Catatan pemeriksa yang cukup panjang. ', 3),
            'check_source' => '1',
            'check_wording' => '1',
            'check_category' => '1',
            'check_duplicate' => '1',
            'check_legal_label' => '1',
        ], $overrides);
    }
}

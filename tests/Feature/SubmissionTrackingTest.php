<?php

namespace Tests\Feature;

use App\Models\PublicRecord;
use App\Tracking\SubmissionCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesTurnstile;
use Tests\TestCase;

class SubmissionTrackingTest extends TestCase
{
    use FakesTurnstile, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_submission_shows_a_code_once_and_stores_only_its_hash(): void
    {
        $this->fakeTurnstile('report');

        $response = $this->post('/reports', $this->report());
        $response->assertRedirect(route('reports.receipt'));

        $receipt = $this->get(route('reports.receipt'));
        $receipt->assertOk();
        $code = $receipt->original->getData()['page']['props']['code'];

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}(?:-[A-Z2-9]{4}){3}$/', $code);

        $record = PublicRecord::query()->first();
        $this->assertNotNull($record);
        $this->assertSame(SubmissionCode::hash($code), $record->tracking_code_hash);
        $this->assertStringNotContainsString($code, json_encode($record->getAttributes()));
        $this->assertStringNotContainsString(str_replace('-', '', $code), json_encode($record->getAttributes()));

        $this->get(route('reports.receipt'))
            ->assertInertia(fn ($page) => $page
                ->component('LaporanDiterima')
                ->where('code', null));

        $this->post('/reports/track', [
            'kind' => 'submission',
            'tracking_code' => strtolower(str_replace('-', '', $code)),
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lacak')
                ->where('kind', 'submission')
                ->where('progress.status', 'menunggu')
                ->where('progress.politician', 'Lacak Uji')
                ->missing('progress.tracking_code'));
    }

    public function test_unknown_code_is_rejected_without_revealing_a_record(): void
    {
        $this->get('/reports/track')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lacak')
                ->where('kind', null)
                ->where('progress', null));

        $this->get('/reports/track?kind=submission')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lacak')
                ->where('kind', 'submission')
                ->where('progress', null));

        $this->post('/reports/track', [
            'kind' => 'submission',
            'tracking_code' => 'AAAA-BBBB-CCCC-DDDD',
        ])->assertSessionHasErrors('tracking_code');

        $this->assertDatabaseCount('public_records', 0);
    }

    public function test_dispute_code_can_be_tracked_separately(): void
    {
        $politician = \App\Models\Politician::create([
            'name' => 'Sanggah Lacak',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan untuk lacak sanggahan.',
            'source_url' => 'https://example.com/sanggah-lacak',
            'source_type' => 'berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);
        $code = SubmissionCode::generate();
        \App\Models\Dispute::create([
            'public_record_id' => $record->id,
            'tracking_code' => $code,
            'reason' => 'Kutipan ini tidak ada di transkrip resmi yang saya tautkan untuk pelacakan.',
            'source_url' => 'https://example.com/transkrip-lacak',
            'source_type' => 'transkrip',
            'status' => \App\Models\Dispute::STATUS_PENDING,
        ]);

        $this->post('/reports/track', [
            'kind' => 'dispute',
            'tracking_code' => strtolower(str_replace('-', '', $code)),
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lacak')
                ->where('kind', 'dispute')
                ->where('progress.status', 'menunggu')
                ->where('progress.category', 'Sanggahan')
                ->where('progress.politician', 'Sanggah Lacak'));

        $this->post('/reports/track', [
            'kind' => 'submission',
            'tracking_code' => $code,
        ])->assertSessionHasErrors('tracking_code');
    }

    /**
     * @return array<string, string>
     */
    private function report(): array
    {
        return $this->withTurnstileToken([
            'name' => 'Lacak Uji',
            'position' => 'Anggota',
            'department' => 'DPR',
            'party_id' => '',
            'category' => 'statement',
            'quote' => 'Kutipan untuk pelacakan anonim.',
            'evtype' => 'berita',
            'link' => 'https://example.com/lacak',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\PublicRecord;
use App\Tracking\SubmissionCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_shows_a_code_once_and_stores_only_its_hash(): void
    {
        $response = $this->withSession(['report_captcha' => 'ABCDE'])->post('/reports', $this->report());

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

        $this->post('/reports/track', ['tracking_code' => strtolower(str_replace('-', '', $code))])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lacak')
                ->where('progress.status', 'menunggu')
                ->where('progress.politician', 'Lacak Uji')
                ->missing('progress.tracking_code'));
    }

    public function test_unknown_code_is_rejected_without_revealing_a_record(): void
    {
        $this->get('/reports/track')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lacak')->where('progress', null));

        $this->post('/reports/track', ['tracking_code' => 'AAAA-BBBB-CCCC-DDDD'])
            ->assertSessionHasErrors('tracking_code');

        $this->assertDatabaseCount('public_records', 0);
    }

    /**
     * @return array<string, string>
     */
    private function report(): array
    {
        return [
            'name' => 'Lacak Uji',
            'position' => 'Anggota',
            'department' => 'DPR',
            'party_id' => '',
            'category' => 'statement',
            'quote' => 'Kutipan untuk pelacakan anonim.',
            'evtype' => 'berita',
            'link' => 'https://example.com/lacak',
            'captcha' => 'ABCDE',
        ];
    }
}

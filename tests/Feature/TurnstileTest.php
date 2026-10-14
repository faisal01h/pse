<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesTurnstile;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use FakesTurnstile, RefreshDatabase;

    public function test_report_rejects_failed_siteverify(): void
    {
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $this->post('/reports', $this->withTurnstileToken([
            'name' => 'Turnstile Uji',
            'position' => 'Anggota',
            'department' => 'DPR',
            'party_id' => '',
            'category' => 'statement',
            'quote' => 'Kutipan untuk uji turnstile.',
            'evtype' => 'berita',
            'link' => 'https://example.com/turnstile',
        ]))->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('public_records', 0);
    }

    public function test_report_rejects_action_mismatch(): void
    {
        $this->fakeTurnstile('wrong-action');

        $this->post('/reports', $this->withTurnstileToken([
            'name' => 'Turnstile Uji',
            'position' => 'Anggota',
            'department' => 'DPR',
            'party_id' => '',
            'category' => 'statement',
            'quote' => 'Kutipan untuk uji turnstile.',
            'evtype' => 'berita',
            'link' => 'https://example.com/turnstile',
        ]))->assertSessionHasErrors('cf-turnstile-response');
    }
}

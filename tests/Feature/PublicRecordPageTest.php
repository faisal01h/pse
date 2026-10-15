<?php

namespace Tests\Feature;

use App\Catalog\ReliabilityLevel;
use App\Models\Politician;
use App\Models\PublicRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicRecordPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_record_page_renders(): void
    {
        $politician = $this->politician();
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan untuk halaman catatan.',
            'source_url' => 'https://example.com/record-page',
            'source_type' => 'Berita',
            'reliability_level' => ReliabilityLevel::HIGH,
            'status' => PublicRecord::STATUS_PUBLISHED,
            'occurred_on' => '2026-02-01',
        ]);

        $this->get(route('records.show', $record))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PublicRecord')
                ->where('record.id', $record->id)
                ->where('record.quote', 'Kutipan untuk halaman catatan.')
                ->where('record.politician.name', 'Tokoh Halaman')
                ->where('record.href', route('records.show', $record)));
    }

    public function test_pending_record_page_is_hidden(): void
    {
        $politician = $this->politician();
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Belum terbit.',
            'source_url' => 'https://example.com/pending-page',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);

        $this->get(route('records.show', $record))->assertNotFound();
    }

    private function politician(): Politician
    {
        return Politician::create([
            'name' => 'Tokoh Halaman',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
    }
}

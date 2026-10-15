<?php

namespace Tests\Feature;

use App\Catalog\ReliabilityLevel;
use App\Models\Politician;
use App\Models\PublicRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRecordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_record_is_returned_as_json(): void
    {
        $politician = $this->politician();
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan publik untuk API.',
            'source_url' => 'https://example.com/api-record',
            'source_type' => 'Berita',
            'reliability_level' => ReliabilityLevel::HIGH,
            'status' => PublicRecord::STATUS_PUBLISHED,
            'occurred_on' => '2026-01-15',
        ]);

        $this->getJson('/api/public-records/'.$record->id)
            ->assertOk()
            ->assertJsonPath('id', $record->id)
            ->assertJsonPath('quote', 'Kutipan publik untuk API.')
            ->assertJsonPath('reliability_level', ReliabilityLevel::HIGH)
            ->assertJsonPath('politician.name', 'Tokoh API')
            ->assertJsonPath('occurred_on', '2026-01-15')
            ->assertJsonPath('href', route('records.show', $record));
    }

    public function test_pending_and_rejected_records_are_hidden(): void
    {
        $politician = $this->politician();

        $pending = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Masih menunggu.',
            'source_url' => 'https://example.com/pending',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);

        $rejected = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Ditolak.',
            'source_url' => 'https://example.com/rejected',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_REJECTED,
        ]);

        $this->getJson('/api/public-records/'.$pending->id)->assertNotFound();
        $this->getJson('/api/public-records/'.$rejected->id)->assertNotFound();
    }

    public function test_record_for_unpublished_politician_is_hidden(): void
    {
        $politician = $this->politician();
        $politician->update(['status' => 0]);

        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Profil tidak terbit.',
            'source_url' => 'https://example.com/hidden-profile',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);

        $this->getJson('/api/public-records/'.$record->id)->assertNotFound();
    }

    public function test_missing_record_returns_not_found(): void
    {
        $this->getJson('/api/public-records/999999')->assertNotFound();
    }

    public function test_public_records_index_is_paginated(): void
    {
        config(['catalog.reports_per_page' => 2]);

        $politician = $this->politician();

        foreach (range(1, 3) as $n) {
            PublicRecord::create([
                'politician_id' => $politician->id,
                'category' => PublicRecord::CATEGORY_STATEMENT,
                'quote' => "Kutipan feed {$n}.",
                'source_url' => "https://example.com/feed-{$n}",
                'source_type' => 'Berita',
                'reliability_level' => ReliabilityLevel::MEDIUM,
                'status' => PublicRecord::STATUS_PUBLISHED,
                'occurred_on' => sprintf('2026-05-%02d', $n),
            ]);
        }

        PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Belum terbit di feed.',
            'source_url' => 'https://example.com/feed-pending',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);

        $this->getJson('/api/public-records')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('meta.current', 1)
            ->assertJsonPath('meta.last', 2)
            ->assertJsonPath('meta.next', 2)
            ->assertJsonCount(2, 'items');

        $this->getJson('/api/public-records?page=2')
            ->assertOk()
            ->assertJsonPath('meta.current', 2)
            ->assertJsonPath('meta.next', null)
            ->assertJsonCount(1, 'items');
    }

    private function politician(): Politician
    {
        return Politician::create([
            'name' => 'Tokoh API',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\SearchDocument;
use App\Search\Embedder;
use App\Search\HashingEmbedder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetInstance(Embedder::class);
        $this->app->singleton(Embedder::class, fn () => new HashingEmbedder(256));
    }

    public function test_search_returns_published_politicians_parties_and_statements(): void
    {
        $party = Party::create(['name' => 'Partai Contoh']);
        $politician = Politician::create([
            'name' => 'Sari Wibowo',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'party_id' => $party->id,
            'status' => 1,
        ]);
        PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Anggaran pendidikan harus naik tahun ini',
            'summary' => null,
            'source_url' => 'https://example.com/pendidikan',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);
        PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_ALLEGATION,
            'quote' => null,
            'summary' => 'Catatan zzrahasiaqq belum boleh tampil',
            'source_url' => 'https://example.com/rahasia',
            'source_type' => 'Berkas',
            'status' => PublicRecord::STATUS_PENDING,
        ]);

        $this->getJson('/search?q=Sari')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'politician',
                'title' => 'Sari Wibowo',
                'href' => '/politicians/'.$politician->id,
            ]);

        $this->getJson('/search?q=Partai%20Contoh')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'party',
                'title' => 'Partai Contoh',
                'href' => '/?party='.$party->id,
            ]);

        $this->getJson('/search?q=pendidikan')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'record',
                'label' => 'Pernyataan publik',
            ]);

        $this->getJson('/search?q=zzrahasiaqq')
            ->assertOk()
            ->assertJson(['results' => []]);

        $this->assertDatabaseMissing('search_documents', [
            'type' => SearchDocument::TYPE_RECORD,
            'title' => 'Catatan zzrahasiaqq belum boleh tampil',
        ]);
    }

    public function test_embedding_matches_a_word_full_text_does_not_contain(): void
    {
        $politician = Politician::create([
            'name' => 'Budi Santosa',
            'position' => 'Menteri',
            'institution' => 'Kementerian Sosial',
            'status' => 1,
        ]);
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_CONVICTION,
            'quote' => null,
            'summary' => 'Terdakwa divonis karena korupsi anggaran bansos',
            'source_url' => 'https://example.com/vonis',
            'source_type' => 'Putusan',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);

        $this->getJson('/search?q=koruptor')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'record',
                'label' => 'Vonis pengadilan',
                'href' => '/politicians/'.$politician->id.'#record-'.$record->id,
            ]);
    }

    public function test_search_page_renders_for_browser_requests(): void
    {
        $this->get('/search?q=arsip')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search')
                ->where('query', 'arsip'));
    }
}

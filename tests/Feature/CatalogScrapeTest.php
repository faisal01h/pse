<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogScrapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_pages_stay_usable_and_the_api_does_not_dump_records(): void
    {
        config(['catalog.api_per_page' => 1]);

        $party = Party::create(['name' => 'Partai Contoh']);
        $first = $this->politician('Andi Awal', $party->id);
        $second = $this->politician('Budi Akhir', $party->id);
        $this->record($first, 'Kutipan Andi yang tidak boleh ada di daftar.');
        $this->record($second, 'Kutipan Budi yang tidak boleh ada di daftar.');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->has('reports', 2)
                ->where('meta.current', 1)
                ->where('meta.next', null));

        $this->get(route('politicians.show', $first))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Politician')
                ->where('politician.records.0.quote', 'Kutipan Andi yang tidak boleh ada di daftar.'));

        $partyPage = $this->get(route('parties.show', $party));
        $partyPage->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Party')
                ->has('politicians', 2)
                ->missing('politicians.0.records')
                ->where('politicians.0.name', 'Andi Awal')
                ->where('meta.next', null));
        $this->assertStringNotContainsString('Kutipan Andi', $partyPage->getContent());

        $this->getJson('/api/politicians?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Andi Awal')
            ->assertJsonPath('meta.next', 2)
            ->assertJsonMissingPath('data.0.records')
            ->assertJsonMissingPath('data.0.quote');

        $listed = $this->getJson('/api/politicians?page=2')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Budi Akhir')
            ->assertJsonPath('meta.prev', 1)
            ->assertJsonPath('meta.current', 2);

        $this->assertStringNotContainsString('Kutipan', $listed->getContent());

        $this->getJson('/api/politicians/'.$first->id)
            ->assertOk()
            ->assertJsonPath('records.0.quote', 'Kutipan Andi yang tidak boleh ada di daftar.');
    }

    public function test_catalog_reads_return_too_many_requests_after_the_per_minute_cap(): void
    {
        config([
            'catalog.per_minute' => 2,
            'catalog.per_hour' => 500,
        ]);

        $this->get('/politicians')->assertOk();
        $this->get('/politicians')->assertOk();
        $this->get('/politicians')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSee('Terlalu banyak permintaan. Coba lagi nanti.');
    }

    private function politician(string $name, ?int $partyId = null): Politician
    {
        return Politician::create([
            'name' => $name,
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'party_id' => $partyId,
            'status' => 1,
        ]);
    }

    private function record(Politician $politician, string $quote): PublicRecord
    {
        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => $quote,
            'source_url' => 'https://example.com/catatan-'.$politician->id,
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);
    }
}

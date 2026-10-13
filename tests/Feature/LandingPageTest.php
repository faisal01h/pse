<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_published_reports_by_latest_date(): void
    {
        $party = Party::create(['name' => 'Partai Contoh']);
        $older = $this->politician('Sari Wibowo', $party->id);
        $newer = $this->politician('Budi Santoso');
        $hidden = $this->politician('Tidak Terbit');
        $hidden->update(['status' => 0]);

        $this->record($older, 'Catatan lama tentang peran negara.', '2024-01-02');
        $latest = $this->record($newer, 'Catatan paling baru tentang subsidi pangan.', '2026-03-04');
        $this->record($older, 'Catatan tanpa tanggal, diterbitkan belakangan.', null, '2026-08-01 10:00:00');
        $this->record($hidden, 'Catatan milik profil yang tidak terbit.', '2026-09-01');
        PublicRecord::create([
            'politician_id' => $older->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Catatan yang masih menunggu pemeriksaan sumber.',
            'source_url' => 'https://example.com/menunggu',
            'source_type' => 'Berita',
            'occurred_on' => '2026-09-02',
            'status' => PublicRecord::STATUS_PENDING,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->has('reports', 3)
                ->where('reports.0.politician.name', 'Sari Wibowo')
                ->where('reports.0.date', '2026-08-01')
                ->where('reports.1.id', $latest->id)
                ->where('reports.1.date', '2026-03-04')
                ->where('reports.2.date', '2024-01-02'));

        $older->update([
            'picture_url' => 'https://example.com/sari.jpg',
            'institution' => 'DPR RI',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reports.0.politician.photo_url', 'https://example.com/sari.jpg')
                ->where('reports.0.politician.institution', 'DPR RI'));

        $this->get('/?party='.$party->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('reports', 2)
                ->where('reports.0.politician.name', 'Sari Wibowo')
                ->where('reports.1.date', '2024-01-02'));

        $this->get('/politicians')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Politicians')
                ->has('politicians', 2)
                ->where('politicians.0.name', 'Budi Santoso')
                ->where('politicians.1.name', 'Sari Wibowo')
                ->where('politicians.1.institution', 'DPR RI'));

        $this->get('/politicians?q=sari')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('politicians', 1));

        $this->get('/parties')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Parties')
                ->has('parties', 1)
                ->where('parties.0.name', 'Partai Contoh')
                ->where('parties.0.published_politicians_count', 1));
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

    private function record(Politician $politician, string $quote, ?string $occurredOn, ?string $reviewedAt = null): PublicRecord
    {
        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => $quote,
            'source_url' => 'https://example.com/catatan-'.$politician->id.'-'.md5($quote),
            'source_type' => 'Berita',
            'occurred_on' => $occurredOn,
            'reviewed_at' => $reviewedAt,
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);
    }
}

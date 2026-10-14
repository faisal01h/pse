<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class SciPolTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_is_public_and_shows_codebook(): void
    {
        $this->get(route('scipol.hub'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SciPol/Index')
                ->has('codebook')
                ->has('tools', 5));
    }

    public function test_compare_shows_only_published_politicians(): void
    {
        $published = $this->politician('Sari Terbit');
        $hidden = Politician::create([
            'name' => 'Tidak Terbit',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 0,
        ]);

        IdeologyPosition::create([
            'politician_id' => $published->id,
            'axis' => IdeologyPosition::AXIS_ECONOMIC,
            'score' => 20,
            'samples' => 1,
            'note' => 'Catatan ekonomi',
            'source_url' => 'https://example.com/ekonomi',
            'source_type' => 'Pidato',
        ]);

        $this->get(route('scipol.compare', [
            'type' => 'politician',
            'ids' => $published->id.','.$hidden->id,
        ]))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SciPol/Bandingkan')
                ->where('subjects', fn ($subjects) => count($subjects) === 1
                    && $subjects[0]['id'] === $published->id
                    && count($subjects[0]['tendencies']) === 1));
    }

    public function test_issue_map_hides_unpublished_politicians(): void
    {
        $issue = Issue::create(['name' => 'Subsidi pangan']);
        $published = $this->politician('Budi Terbit');
        $hidden = Politician::create([
            'name' => 'Budi Draft',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 0,
        ]);

        IssueStance::create([
            'issue_id' => $issue->id,
            'politician_id' => $published->id,
            'position' => IssueStance::SUPPORT,
            'note' => 'Mendukung',
            'source_url' => 'https://example.com/dukung',
            'source_type' => 'Pidato',
        ]);
        IssueStance::create([
            'issue_id' => $issue->id,
            'politician_id' => $hidden->id,
            'position' => IssueStance::SUPPORT,
            'note' => 'Tidak boleh tampil',
            'source_url' => 'https://example.com/hidden',
            'source_type' => 'Pidato',
        ]);

        $this->get(route('scipol.issues.show', $issue))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SciPol/IsuShow')
                ->where('groups.dukung', fn ($rows) => count($rows) === 1
                    && $rows[0]['subject']['id'] === $published->id));
    }

    public function test_affiliations_and_topics_list_published_rows(): void
    {
        $politician = $this->politician('Sari Afiliasi');
        $business = Business::create([
            'name' => 'PT Contoh',
            'sector' => 'Energi',
            'status' => Business::STATUS_PUBLISHED,
        ]);
        BusinessAffiliation::create([
            'politician_id' => $politician->id,
            'business_id' => $business->id,
            'type' => BusinessAffiliation::TYPE_OWNERSHIP,
            'ownership_percent' => 12.5,
            'summary' => 'Kepemilikan tercatat',
            'source_url' => 'https://example.com/aff',
            'source_type' => 'Laporan',
            'status' => BusinessAffiliation::STATUS_PUBLISHED,
        ]);

        $topic = Topic::create(['name' => 'Energi']);
        $record = PublicRecord::query()->where('politician_id', $politician->id)->first();
        $record->topics()->attach($topic->id);

        $this->get(route('scipol.affiliations', ['sector' => 'Energi']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SciPol/Afiliasi')
                ->has('items', 1)
                ->where('items.0.business.name', 'PT Contoh'));

        $this->get(route('scipol.topics', ['topic' => $topic->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SciPol/Topik')
                ->has('items', 1)
                ->where('items.0.politician.name', 'Sari Afiliasi'));
    }

    public function test_practice_page_links_to_existing_flows(): void
    {
        $this->get(route('scipol.practice'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SciPol/Latihan')
                ->where('report_url', route('reports.create'))
                ->where('dispute_url', route('dispute.create')));
    }

    public function test_suggest_returns_published_matches(): void
    {
        $this->politician('Anita Published');
        Politician::create([
            'name' => 'Anita Draft',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 0,
        ]);

        $this->getJson(route('scipol.suggest', ['type' => 'politician', 'q' => 'Anita']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Anita Published']);
    }

    public function test_xlsx_export_contains_required_sheets(): void
    {
        $politician = $this->politician('Ekspor Subjek');
        IdeologyPosition::create([
            'politician_id' => $politician->id,
            'axis' => IdeologyPosition::AXIS_SOCIAL,
            'score' => 40,
            'samples' => 2,
            'note' => 'Catatan sosial',
            'source_url' => 'https://example.com/sosial',
            'source_type' => 'Pidato',
        ]);

        $response = $this->get(route('scipol.export', [
            'scope' => 'compare',
            'type' => 'politician',
            'ids' => (string) $politician->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );

        $path = tempnam(sys_get_temp_dir(), 'scipol-test').'.xlsx';
        file_put_contents($path, file_get_contents($response->getFile()->getPathname()));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $zip->close();

        $reader = new Reader;
        $reader->open($path);
        $names = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }
        $reader->close();
        @unlink($path);

        $this->assertSame(
            ['Meta', 'Codebook', 'Subjects', 'Axes', 'Stances', 'Affiliations', 'Records'],
            $names
        );
    }

    private function politician(string $name): Politician
    {
        $politician = Politician::create([
            'name' => $name,
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 1,
        ]);

        PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan publik untuk '.$name,
            'source_url' => 'https://example.com/'.str_replace(' ', '-', strtolower($name)),
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);

        return $politician;
    }
}

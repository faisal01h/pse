<?php

namespace Tests\Feature;

use App\Catalog\TemporalGraph;
use App\Catalog\TemporalIntervalWriter;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporalRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_superseding_a_stance_preserves_history_and_as_of_queries(): void
    {
        $user = User::factory()->create();
        $politician = $this->politician('Sari Wibowo');
        $note = 'Sumber menyebut dukungan subsidi pangan secara eksplisit dalam pidato resmi.';

        $this->actingAs($user)
            ->post(route('admin.politicians.stances.store', $politician), [
                'issue_name' => 'Subsidi pangan',
                'position' => IssueStance::SUPPORT,
                'note' => $note,
                'source_url' => 'https://example.com/dukung',
                'source_type' => 'Pidato',
                'occurred_on' => '2024-01-10',
            ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.politicians.stances.store', $politician), [
                'issue_name' => 'Subsidi pangan',
                'position' => IssueStance::OPPOSE,
                'note' => 'Sumber kemudian menyebut penolakan terhadap perluasan subsidi pangan tersebut.',
                'source_url' => 'https://example.com/tolak',
                'source_type' => 'Wawancara',
                'occurred_on' => '2025-06-01',
            ])->assertRedirect();

        $this->assertSame(2, IssueStance::query()->count());
        $this->assertSame(1, IssueStance::query()->open()->count());
        $this->assertSame(IssueStance::OPPOSE, IssueStance::query()->open()->value('position'));

        $temporal = app(TemporalGraph::class);
        $asOf = $temporal->stancesAsOf('2024-06-01', $politician->id, null);
        $this->assertCount(1, $asOf);
        $this->assertSame(IssueStance::SUPPORT, $asOf->first()->position);

        $current = $temporal->currentStances($politician->id, null);
        $this->assertCount(1, $current);
        $this->assertSame(IssueStance::OPPOSE, $current->first()->position);

        $this->get(route('politicians.show', $politician))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('politician.issue_stances.0.position_label', 'Tolak')
                ->has('politician.stance_timeline', 2));
    }

    public function test_ending_a_coalition_allows_a_new_open_interval(): void
    {
        $user = User::factory()->create();
        $a = Party::create(['name' => 'Partai A']);
        $b = Party::create(['name' => 'Partai B']);
        $summary = 'Kedua partai disebut berada dalam koalisi yang sama pada pemilu tersebut.';

        $this->actingAs($user)
            ->post(route('admin.parties.links.store', $a), [
                'other_id' => $b->id,
                'type' => PartyLink::TYPE_COALITION,
                'summary' => $summary,
                'source_url' => 'https://example.com/koalisi-1',
                'source_type' => 'Berita',
                'occurred_on' => '2023-01-01',
            ])->assertRedirect();

        $link = PartyLink::query()->open()->first();
        $this->assertNotNull($link);

        $this->actingAs($user)
            ->delete(route('admin.parties.links.destroy', [$a, $link]))
            ->assertRedirect();

        $this->assertNull(PartyLink::query()->open()->first());
        $this->assertNotNull($link->fresh()->ended_at);

        $this->actingAs($user)
            ->post(route('admin.parties.links.store', $a), [
                'other_id' => $b->id,
                'type' => PartyLink::TYPE_COALITION,
                'summary' => $summary.' Diperbarui setelah rekonsiliasi publik.',
                'source_url' => 'https://example.com/koalisi-2',
                'source_type' => 'Berita',
                'occurred_on' => '2026-01-01',
            ])->assertRedirect();

        $this->assertSame(2, PartyLink::query()->count());
        $this->assertSame(1, PartyLink::query()->open()->count());
    }

    public function test_open_politician_link_rejects_active_duplicate(): void
    {
        $writer = app(TemporalIntervalWriter::class);
        $a = $this->politician('A');
        $b = $this->politician('B');
        [$left, $right] = PoliticianLink::endpoints(PoliticianLink::TYPE_ALLY, $a->id, $b->id);

        $writer->openPoliticianLink([
            'politician_a_id' => $left,
            'politician_b_id' => $right,
            'type' => PoliticianLink::TYPE_ALLY,
            'summary' => 'Keduanya disebut satu koalisi dalam pemberitaan kampanye daerah itu.',
            'source_url' => 'https://example.com/sekutu',
            'source_type' => 'Berita',
            'occurred_on' => '2024-01-01',
            'user_id' => null,
        ]);

        $this->expectException(\App\Catalog\TemporalDuplicateException::class);
        $writer->openPoliticianLink([
            'politician_a_id' => $left,
            'politician_b_id' => $right,
            'type' => PoliticianLink::TYPE_ALLY,
            'summary' => 'Keduanya disebut satu koalisi dalam pemberitaan kampanye daerah itu lagi.',
            'source_url' => 'https://example.com/sekutu-2',
            'source_type' => 'Berita',
            'occurred_on' => '2024-02-01',
            'user_id' => null,
        ]);
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
            'quote' => 'Kutipan publik untuk profil '.$name,
            'source_url' => 'https://example.com/'.str_replace(' ', '-', strtolower($name)),
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);

        return $politician;
    }
}

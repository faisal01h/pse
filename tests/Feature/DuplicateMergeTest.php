<?php

namespace Tests\Feature;

use App\Duplicates\DuplicateDetector;
use App\Duplicates\NameNormalizer;
use App\Duplicates\RecordMerger;
use App\Models\Audit;
use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Models\RecordMerge;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Plugins\RealPolitik\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SeedsAuthorization;
use Tests\TestCase;

class DuplicateMergeTest extends TestCase
{
    use RefreshDatabase, SeedsAuthorization;

    public function test_normalizer_ignores_case_titles_degrees_and_punctuation(): void
    {
        $n = app(NameNormalizer::class);

        $this->assertSame(
            $n->normalize('politician', 'Budi  Santoso'),
            $n->normalize('politician', 'Dr. Ir. H. BUDI SANTOSO, S.H., M.Si.'),
        );
        $this->assertSame($n->normalize('party', 'Partai Golkar'), $n->normalize('party', 'GOLKAR'));
        $this->assertSame($n->normalize('business', 'PT. Maju Jaya Tbk'), $n->normalize('business', 'maju  jaya'));
        $this->assertNotSame($n->normalize('politician', 'Budi Santoso'), $n->normalize('politician', 'Budi Hartono'));
    }

    public function test_detector_lists_exact_and_similar_pairs(): void
    {
        $a = $this->politician('Budi Santoso');
        $b = $this->politician('Dr. Budi Santoso, S.H.');
        $c = $this->politician('Budi Santosa');
        $this->politician('Citra Lestari');

        $pairs = collect(app(DuplicateDetector::class)->pairs('politician'));

        $exact = $pairs->firstWhere('kind', 'exact');
        $this->assertSame([$a->id, $b->id], [$exact['a']->id, $exact['b']->id]);
        $this->assertTrue($pairs->contains(fn ($p) => $p['kind'] === 'similar' && $p['b']->id === $c->id));
        $this->assertCount(3, $pairs);
    }

    public function test_admin_page_lists_candidates_and_requires_permission(): void
    {
        $this->politician('Budi Santoso');
        $this->politician('budi santoso');

        $this->actingAs($this->makePlainUser())->get(route('admin.duplicates.index'))->assertForbidden();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.duplicates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Duplicates')->has('pairs', 1));
    }

    public function test_creating_a_politician_with_a_matching_name_needs_confirmation(): void
    {
        $admin = $this->makeAdmin();
        $this->politician('Budi Santoso');
        $payload = ['name' => 'H. Budi Santoso', 'position' => 'Menteri', 'institution' => 'Kementerian', 'status' => 0];

        $this->actingAs($admin)->post(route('admin.politicians.store'), $payload)->assertSessionHasErrors('name');
        $this->assertSame(1, Politician::query()->count());

        $this->actingAs($admin)->post(route('admin.politicians.store'), $payload + ['confirm_duplicate' => true])->assertRedirect();
        $this->assertSame(2, Politician::query()->count());
    }

    public function test_creating_a_party_or_business_with_a_matching_name_is_blocked(): void
    {
        $admin = $this->makeAdmin();
        Party::create(['name' => 'Partai Golkar']);
        Business::create(['name' => 'PT Maju Jaya', 'status' => 1]);

        $this->actingAs($admin)->post(route('admin.parties.store'), ['name' => 'golkar'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.businesses.store'), ['name' => 'Maju Jaya, PT.', 'status' => 1])->assertSessionHasErrors('name');

        $this->assertSame(1, Party::query()->count());
        $this->assertSame(1, Business::query()->count());
        $this->assertSame(Business::query()->first()->id, Business::findOrCreateByName('pt maju jaya')->id);
    }

    public function test_public_submission_reuses_a_normalized_politician_match(): void
    {
        $existing = $this->politician('Budi Santoso');

        $found = app(DuplicateDetector::class)->findPolitician('Dr. BUDI SANTOSO', 'kementerian  ');

        $this->assertSame($existing->id, $found?->id);
        $this->assertNull(app(DuplicateDetector::class)->findPolitician('Budi Santoso', 'DPR'));
    }

    public function test_merging_politicians_reassigns_every_reference(): void
    {
        $admin = $this->makeAdmin();
        $canonical = $this->politician('Budi Santoso');
        $source = $this->politician('Budi Santoso S.H.');
        $other = $this->politician('Lain Orang');
        $source->update(['picture_url' => 'politicians/x.jpg']);
        $business = Business::create(['name' => 'PT Maju', 'status' => 1]);
        $issue = Issue::create(['name' => 'Subsidi']);

        $record = $this->record($source);
        BusinessAffiliation::create([
            'politician_id' => $source->id, 'business_id' => $business->id, 'type' => 'ownership',
            'summary' => 'x', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'status' => 1,
        ]);

        // Both sides hold an open stance and tendency on the same key.
        IssueStance::create(['issue_id' => $issue->id, 'politician_id' => $canonical->id, 'position' => 'dukung', 'note' => 'n', 'summary' => 's', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'effective_from' => '2024-01-01']);
        $newer = IssueStance::create(['issue_id' => $issue->id, 'politician_id' => $source->id, 'position' => 'tolak', 'note' => 'n', 'summary' => 's', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'effective_from' => '2025-03-01']);
        $axis = ['note' => 'n', 'axis' => 'ekonomi', 'score' => 70, 'samples' => 1, 'note' => 'n', 'summary' => 's', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'effective_from' => '2024-05-01'];
        IdeologyPosition::create($axis + ['politician_id' => $canonical->id]);
        $dupe = IdeologyPosition::create($axis + ['politician_id' => $source->id]);
        $dupe->basisRecords()->attach($record->id);

        PoliticianLink::create(['politician_a_id' => $source->id, 'politician_b_id' => $other->id, 'type' => 'sekutu', 'note' => 'n', 'summary' => 's', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'effective_from' => '2024-01-01']);
        PoliticianLink::create(['politician_a_id' => $canonical->id, 'politician_b_id' => $source->id, 'type' => 'lawan', 'note' => 'n', 'summary' => 's', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'effective_from' => '2024-01-01']);

        $signal = PowerSignal::create(['type' => 'drift', 'severity' => 'medium', 'politician_id' => $source->id, 'title' => 'T']);
        $watcher = $this->makePlainUser();
        WatchlistItem::create(['user_id' => $admin->id, 'watchable_type' => Politician::class, 'watchable_id' => $source->id]);
        WatchlistItem::create(['user_id' => $admin->id, 'watchable_type' => Politician::class, 'watchable_id' => $canonical->id]);
        WatchlistItem::create(['user_id' => $watcher->id, 'watchable_type' => Politician::class, 'watchable_id' => $source->id]);

        $this->actingAs($admin)
            ->post(route('admin.duplicates.merge'), ['type' => 'politician', 'canonical_id' => $canonical->id, 'source_id' => $source->id])
            ->assertRedirect(route('admin.duplicates.index', ['type' => 'politician']));

        $this->assertNull(Politician::find($source->id));
        $this->assertSame($canonical->id, $record->fresh()->politician_id);
        $this->assertSame(1, BusinessAffiliation::query()->where('politician_id', $canonical->id)->count());
        $this->assertSame($canonical->id, $signal->fresh()->politician_id);
        $this->assertSame('politicians/x.jpg', $canonical->fresh()->picture_url);

        // Stances: older open one is closed the day before the newer begins.
        $this->assertSame(1, IssueStance::query()->where('politician_id', $canonical->id)->open()->count());
        $this->assertSame('2025-02-28', IssueStance::query()->whereDate('effective_from', '2024-01-01')->first()->ended_at->toDateString());
        $this->assertNull($newer->fresh()->ended_at);

        // Identical tendency collapsed; its basis record preserved.
        $this->assertSame(1, IdeologyPosition::query()->where('politician_id', $canonical->id)->count());
        $this->assertSame([$record->id], IdeologyPosition::query()->first()->basisRecords->pluck('id')->all());

        // Link to self removed, the surviving link points at the canonical.
        $this->assertSame(1, PoliticianLink::query()->count());
        $this->assertSame([$canonical->id, $other->id], PoliticianLink::query()->get(['politician_a_id', 'politician_b_id'])->map(fn ($l) => [$l->politician_a_id, $l->politician_b_id])->first());

        // Watchlists: no duplicate for the admin, the other user's item moved.
        $this->assertSame(1, WatchlistItem::query()->where('user_id', $admin->id)->count());
        $this->assertSame($canonical->id, WatchlistItem::query()->where('user_id', $watcher->id)->value('watchable_id'));

        $merge = RecordMerge::query()->first();
        $this->assertSame([$source->id, $canonical->id, $admin->id], [$merge->source_id, $merge->canonical_id, $merge->user_id]);
        $this->assertSame('Budi Santoso S.H.', $merge->snapshot['name']);
        $this->assertTrue(Audit::query()->where('action', Audit::ACTION_MERGED)->where('auditable_id', $canonical->id)->exists());

        // The old profile URL now redirects to the survivor.
        $this->get('/politicians/'.$source->id)->assertRedirect('/politicians/'.$canonical->id)->assertStatus(301);
        $this->get('/politicians/999999')->assertNotFound();
    }

    public function test_merging_parties_moves_members_stances_and_watchlists(): void
    {
        $admin = $this->makeAdmin();
        $canonical = Party::create(['name' => 'Golkar']);
        $source = Party::create(['name' => 'Partai Golkar', 'logo_path' => 'parties/g.png']);
        $member = $this->politician('Anggota Satu');
        $member->update(['party_id' => $source->id]);
        $issue = Issue::create(['name' => 'Pajak']);
        IssueStance::create(['issue_id' => $issue->id, 'party_id' => $source->id, 'position' => 'dukung', 'note' => 'n', 'summary' => 's', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'effective_from' => '2024-01-01']);
        $signal = PowerSignal::create(['type' => 'drift', 'severity' => 'low', 'party_id' => $source->id, 'title' => 'T']);
        WatchlistItem::create(['user_id' => $admin->id, 'watchable_type' => Party::class, 'watchable_id' => $source->id]);

        app(RecordMerger::class)->merge('party', $canonical, $source, $admin);

        $this->assertNull(Party::find($source->id));
        $this->assertSame($canonical->id, $member->fresh()->party_id);
        $this->assertSame($canonical->id, IssueStance::query()->value('party_id'));
        $this->assertSame($canonical->id, $signal->fresh()->party_id);
        $this->assertSame($canonical->id, WatchlistItem::query()->value('watchable_id'));
        $this->assertSame('parties/g.png', $canonical->fresh()->logo_path);
        $this->get('/parties/'.$source->id)->assertRedirect('/parties/'.$canonical->id);
    }

    public function test_merging_businesses_moves_affiliations(): void
    {
        $canonical = Business::create(['name' => 'PT Maju', 'status' => 0]);
        $source = Business::create(['name' => 'Maju', 'sector' => 'Energi', 'status' => 1]);
        $politician = $this->politician('Budi');
        BusinessAffiliation::create([
            'politician_id' => $politician->id, 'business_id' => $source->id, 'type' => 'ownership',
            'summary' => 'x', 'source_url' => 'https://e.com', 'source_type' => 'Berita', 'status' => 1,
        ]);

        app(RecordMerger::class)->merge('business', $canonical, $source);

        $this->assertNull(Business::find($source->id));
        $this->assertSame($canonical->id, BusinessAffiliation::query()->value('business_id'));
        $this->assertSame('Energi', $canonical->fresh()->sector);
        $this->assertSame(1, (int) $canonical->fresh()->status);
    }

    public function test_every_foreign_key_to_merged_tables_is_handled_by_the_merger(): void
    {
        $handled = [
            'politicians' => RecordMerger::COLUMN_REFERENCES['politician'],
            'parties' => RecordMerger::COLUMN_REFERENCES['party'],
            'businesses' => RecordMerger::COLUMN_REFERENCES['business'],
        ];

        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $foreign) {
                if (! isset($handled[$foreign['foreign_table']])) {
                    continue;
                }

                $this->assertContains(
                    $foreign['columns'][0],
                    $handled[$foreign['foreign_table']][$table['name']] ?? [],
                    "{$table['name']}.{$foreign['columns'][0]} references {$foreign['foreign_table']} but RecordMerger does not reassign it",
                );
            }
        }

        // Morph references are not real foreign keys; keep this list in sync by hand.
        $this->assertSame(['realpolitik_watchlists', 'autovet_runs'], array_keys(RecordMerger::MORPH_REFERENCES));
        $this->assertTrue(Schema::hasColumn('audits', 'auditable_type'));
        $this->assertSame(0, DB::table('record_merges')->count());
    }

    private function politician(string $name, array $attributes = []): Politician
    {
        return Politician::create($attributes + [
            'name' => $name,
            'position' => 'Menteri',
            'institution' => 'Kementerian',
            'status' => 0,
        ]);
    }

    private function record(Politician $politician): PublicRecord
    {
        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan.',
            'source_url' => 'https://e.com/a',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);
    }
}

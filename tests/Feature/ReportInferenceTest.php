<?php

namespace Tests\Feature;

use App\Inference\HttpReportReader;
use App\Inference\LocalReportReader;
use App\Inference\ReportReader;
use App\Models\Audit;
use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Models\RelationshipProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use MarcReichel\Laya\Laya;
use Tests\TestCase;

class ReportInferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_a_report_proposes_a_link_without_showing_it(): void
    {
        $user = User::factory()->create();
        $subject = $this->politician('Sari Wibowo');
        $other = $this->politician('Budi Santoso');
        $other->update(['status' => 1]);
        $record = $this->pendingRecord($subject, 'Sari mendukung Budi Santoso dalam pembahasan subsidi pangan hari ini juga.');
        Issue::create(['name' => 'Subsidi pangan']);

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($subject, $record->quote))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, PoliticianLink::query()->count());
        $this->assertDatabaseHas('relationship_proposals', [
            'public_record_id' => $record->id,
            'kind' => RelationshipProposal::KIND_LINK,
            'status' => RelationshipProposal::STATUS_PENDING,
        ]);

        $this->get(route('politicians.show', $subject))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('politician.connections', 0));

        $proposal = RelationshipProposal::query()->where('kind', RelationshipProposal::KIND_LINK)->first();
        $this->actingAs($user)
            ->post(route('admin.proposals.accept', $proposal))
            ->assertRedirect();

        $link = PoliticianLink::query()->first();
        $this->assertSame($record->source_url, $link->source_url);
        $this->assertSame(PoliticianLink::TYPE_SUPPORT, $link->type);
        $this->get(route('politicians.show', $subject))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('politician.connections', 1)
                ->where('politician.connections.0.other.name', 'Budi Santoso'));
    }

    public function test_a_short_excerpt_needs_an_admin_note_before_it_is_public(): void
    {
        $user = User::factory()->create();
        $subject = $this->politician('Sari Wibowo');
        $record = $this->pendingRecord($subject, 'Tambah subsidi sekarang.');

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($subject, $record->quote))
            ->assertRedirect();

        $proposal = RelationshipProposal::query()->where('kind', RelationshipProposal::KIND_AXIS)->first();
        $this->assertNotNull($proposal);

        $this->actingAs($user)
            ->post(route('admin.proposals.accept', $proposal), [])
            ->assertSessionHasErrors('note');
        $this->assertSame(0, IdeologyPosition::query()->count());

        $this->actingAs($user)
            ->post(route('admin.proposals.accept', $proposal), [
                'note' => 'Sumber yang terbit meminta subsidi ditambah, tanpa skor ideologi lain.',
            ])
            ->assertRedirect();
        $position = IdeologyPosition::query()->first();
        $this->assertNotNull($position);
        $this->assertSame(1, $position->samples);
        $this->assertSame(35, $position->score);
    }

    public function test_accepting_another_reading_on_the_same_axis_averages_the_score(): void
    {
        $user = User::factory()->create();
        $subject = $this->politician('Sari Wibowo');
        $record = $this->pendingRecord($subject, 'Sari mendukung subsidi yang lebih besar bagi harga pangan rakyat.');
        $record->update(['status' => PublicRecord::STATUS_PUBLISHED]);

        IdeologyPosition::create([
            'politician_id' => $subject->id,
            'axis' => IdeologyPosition::AXIS_ECONOMIC,
            'score' => 20,
            'samples' => 1,
            'note' => 'Catatan lama yang cukup panjang untuk memenuhi batas minimum karakter.',
            'source_url' => 'https://example.com/lama',
            'source_type' => 'Berita',
        ]);

        $first = RelationshipProposal::create([
            'public_record_id' => $record->id,
            'kind' => RelationshipProposal::KIND_AXIS,
            'fingerprint' => 'axis:ekonomi:first',
            'payload' => ['axis' => 'ekonomi', 'score' => 80],
            'excerpt' => 'Sumber baru menempatkan ekonomi lebih ke pasar daripada catatan sebelumnya.',
            'status' => RelationshipProposal::STATUS_PENDING,
        ]);

        $this->actingAs($user)->post(route('admin.proposals.accept', $first))->assertRedirect();

        $position = IdeologyPosition::query()->where('axis', 'ekonomi')->first();
        $this->assertSame(50, $position->score);
        $this->assertSame(2, $position->samples);
        $this->assertTrue($position->basisRecords()->where('public_records.id', $record->id)->exists());

        $otherRecord = PublicRecord::create([
            'politician_id' => $subject->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Sari mendorong pasar bebas untuk distribusi pangan nasional.',
            'source_url' => 'https://example.com/pasar',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);

        $second = RelationshipProposal::create([
            'public_record_id' => $otherRecord->id,
            'kind' => RelationshipProposal::KIND_AXIS,
            'fingerprint' => 'axis:ekonomi:second',
            'payload' => ['axis' => 'ekonomi', 'score' => 100],
            'excerpt' => 'Sumber ketiga menempatkan ekonomi sepenuhnya pada orientasi pasar.',
            'status' => RelationshipProposal::STATUS_PENDING,
        ]);

        $this->actingAs($user)->post(route('admin.proposals.accept', $second))->assertRedirect();

        $position->refresh();
        $this->assertSame(67, $position->score);
        $this->assertSame(3, $position->samples);
        $this->assertEqualsCanonicalizing(
            [$record->id, $otherRecord->id],
            $position->basisRecords()->pluck('public_records.id')->all()
        );

        $subject->update(['status' => 1]);

        $this->get(route('politicians.show', $subject))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('politician.tendencies.0.records', 2)
                ->where('politician.tendencies.0.records', function ($records) use ($record, $otherRecord) {
                    $ids = collect($records)->pluck('id')->all();

                    return empty(array_diff([$record->id, $otherRecord->id], $ids));
                }));

        $this->actingAs($user)->post(route('admin.politicians.tendencies.store', $subject), [
            'axis' => 'ekonomi',
            'score' => 10,
            'note' => 'Penyunting menempatkan ulang sumbu ini berdasarkan satu sumber yang lebih lengkap.',
            'source_url' => 'https://example.com/sunting',
            'source_type' => 'Pidato',
        ])->assertRedirect();

        // A manual write supersedes: the averaged reading is closed, a fresh interval opens.
        $position->refresh();
        $this->assertSame(67, $position->score);
        $this->assertNotNull($position->ended_at);

        $current = IdeologyPosition::query()->where('axis', 'ekonomi')->open()->first();
        $this->assertNotSame($position->id, $current->id);
        $this->assertSame(10, $current->score);
        $this->assertSame(1, $current->samples);
        $this->assertSame(0, $current->basisRecords()->count());
    }

    public function test_the_laya_driver_proposes_from_typed_answers(): void
    {
        $user = User::factory()->create();
        $subject = $this->politician('Sari Wibowo');
        $other = $this->politician('Budi Santoso');
        $issue = Issue::create(['name' => 'Subsidi pangan']);
        $record = $this->pendingRecord($subject, 'Sari mendukung Budi Santoso dalam pembahasan subsidi pangan hari ini juga.');

        config(['inference.driver' => 'laya']);
        Laya::fake([
            'present.ekonomi' => true,
            'score.ekonomi' => 0.8,
            'present.sosial' => false,
            'score.sosial' => 0,
            'present.nasionalisme' => false,
            'score.nasionalisme' => 0,
            'stance.'.$issue->id => 'dukung',
            'link.'.$other->id => 'mendukung',
        ]);

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($subject, $record->quote))
            ->assertRedirect();

        $this->assertDatabaseHas('relationship_proposals', [
            'public_record_id' => $record->id,
            'kind' => RelationshipProposal::KIND_AXIS,
            'status' => RelationshipProposal::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('audits', [
            'public_record_id' => $record->id,
            'action' => Audit::ACTION_INFERENCE,
            'method' => 'laya:multilingual',
        ]);
        $this->assertDatabaseHas('relationship_proposals', [
            'public_record_id' => $record->id,
            'kind' => RelationshipProposal::KIND_LINK,
            'status' => RelationshipProposal::STATUS_PENDING,
        ]);
    }

    public function test_rejecting_a_report_creates_no_proposal(): void
    {
        $user = User::factory()->create();
        $subject = $this->politician('Sari Wibowo');
        $record = $this->pendingRecord($subject, 'Sari mendukung subsidi yang lebih besar bagi harga pangan.');

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($subject, $record->quote, 'reject'))
            ->assertRedirect();

        $this->assertSame(0, RelationshipProposal::query()->count());
    }

    public function test_a_failed_http_reader_falls_back_to_the_local_reader(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));
        $this->app->bind(ReportReader::class, fn () => new HttpReportReader(
            'https://example.test/chat',
            'key',
            'model',
            1,
            new LocalReportReader,
        ));

        $user = User::factory()->create();
        $subject = $this->politician('Sari Wibowo');
        $record = $this->pendingRecord($subject, 'Sari mendukung subsidi yang lebih besar bagi harga pangan rakyat.');

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($subject, $record->quote))
            ->assertRedirect();

        $this->assertDatabaseHas('relationship_proposals', [
            'public_record_id' => $record->id,
            'kind' => RelationshipProposal::KIND_AXIS,
            'status' => RelationshipProposal::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('audits', [
            'public_record_id' => $record->id,
            'action' => Audit::ACTION_INFERENCE,
            'method' => 'local',
        ]);
    }

    public function test_rereading_replaces_pending_proposals_and_keeps_dismissed_ones(): void
    {
        $user = User::factory()->create();
        $subject = $this->politician('Sari Wibowo');
        $record = $this->pendingRecord($subject, 'Sari mendukung subsidi yang lebih besar bagi harga pangan rakyat.');

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($subject, $record->quote))
            ->assertRedirect();

        $proposal = RelationshipProposal::query()->first();
        $this->actingAs($user)->post(route('admin.proposals.dismiss', $proposal))->assertRedirect();

        $record->refresh();
        $changed = 'Sari menentang kesetaraan dalam rapat anggaran yang sama hari ini.';
        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($subject, $changed))
            ->assertRedirect();

        $this->assertDatabaseHas('relationship_proposals', [
            'id' => $proposal->id,
            'status' => RelationshipProposal::STATUS_DISMISSED,
        ]);
        $this->assertTrue(RelationshipProposal::query()
            ->where('status', RelationshipProposal::STATUS_PENDING)
            ->where('excerpt', $changed)
            ->exists());
    }

    private function politician(string $name): Politician
    {
        return Politician::create([
            'name' => $name,
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 0,
        ]);
    }

    private function pendingRecord(Politician $politician, string $quote): PublicRecord
    {
        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => $quote,
            'source_url' => 'https://example.com/laporan-'.$politician->id,
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);
    }

    private function decision(Politician $politician, string $quote, string $decision = 'publish'): array
    {
        return [
            'decision' => $decision,
            'name' => $politician->name,
            'position' => $politician->position,
            'department' => $politician->institution,
            'party_id' => '',
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => $quote,
            'summary' => '',
            'source_type' => 'Berita',
            'source_url' => 'https://example.com/laporan-'.$politician->id,
            'review_note' => 'Sumber terbuka, kutipan sesuai, dan jenis catatan memang pernyataan publik.',
            'reliability_level' => 'high',
            'check_source' => '1',
            'check_wording' => '1',
            'check_category' => '1',
            'check_duplicate' => '1',
            'check_legal_label' => '1',
        ];
    }
}

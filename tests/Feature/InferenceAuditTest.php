<?php

namespace Tests\Feature;

use App\Inference\ReportReader;
use App\Models\Audit;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\RelationshipProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InferenceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_a_report_writes_an_audit(): void
    {
        $user = User::factory()->create();
        $politician = $this->politician('Sari Wibowo');
        $record = $this->record($politician, 'Sari mendukung subsidi yang lebih besar bagi harga pangan rakyat.');

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($politician, $record->quote))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('audits', [
            'user_id' => $user->id,
            'public_record_id' => $record->id,
            'trigger' => Audit::TRIGGER_PUBLISH,
            'method' => 'local',
            'status' => Audit::STATUS_RAN,
        ]);
        $this->assertGreaterThan(0, Audit::query()->where('action', Audit::ACTION_INFERENCE)->value('proposals_created'));
    }

    public function test_guest_cannot_trigger_inference(): void
    {
        $record = $this->record($this->politician('Sari Wibowo'), 'Teks.', PublicRecord::STATUS_PUBLISHED);

        $this->post(route('admin.audits.records.run', $record))->assertRedirect('/login');
        $this->post(route('admin.audits.run'))->assertRedirect('/login');
        $this->assertSame(0, Audit::query()->where('action', Audit::ACTION_INFERENCE)->count());
    }

    public function test_admin_can_trigger_inference_for_one_published_record(): void
    {
        $user = User::factory()->create();
        $politician = $this->politician('Sari Wibowo');
        $record = $this->record($politician, 'Sari mendukung subsidi yang lebih besar bagi harga pangan rakyat.', PublicRecord::STATUS_PUBLISHED);
        $hidden = $this->record($politician, 'Sari mendukung subsidi lain yang belum terbit.', PublicRecord::STATUS_PENDING);

        $this->actingAs($user)
            ->post(route('admin.audits.records.run', $record))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('audits', [
            'public_record_id' => $record->id,
            'trigger' => Audit::TRIGGER_MANUAL,
            'status' => Audit::STATUS_RAN,
        ]);
        $this->assertDatabaseHas('relationship_proposals', [
            'public_record_id' => $record->id,
            'status' => RelationshipProposal::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->post(route('admin.audits.records.run', $hidden))
            ->assertNotFound();
        $this->assertDatabaseMissing('audits', ['public_record_id' => $hidden->id]);
    }

    public function test_admin_can_trigger_inference_for_every_published_record(): void
    {
        $user = User::factory()->create();
        $politician = $this->politician('Sari Wibowo');
        $published = $this->record($politician, 'Sari mendukung subsidi yang lebih besar bagi harga pangan rakyat.', PublicRecord::STATUS_PUBLISHED);
        $this->record($politician, 'Belum terbit.', PublicRecord::STATUS_PENDING);

        $this->actingAs($user)
            ->post(route('admin.audits.run'))
            ->assertRedirect(route('admin.audits.index'))
            ->assertSessionHas('toast.type', 'success');

        $inference = Audit::query()->where('action', Audit::ACTION_INFERENCE);
        $this->assertSame(1, $inference->count());
        $this->assertSame($published->id, (int) $inference->value('public_record_id'));

        $this->actingAs($user)
            ->get(route('admin.audits.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Audits')
                ->has('records', 1)
                ->where('audits.0.action', Audit::ACTION_INFERENCE)
                ->where('audits.0.detail', fn ($detail) => str_contains($detail, 'Manual') && str_contains($detail, 'Lokal')));
    }

    public function test_a_failed_inference_flashes_a_failure_toast(): void
    {
        $this->app->bind(ReportReader::class, fn () => new class implements ReportReader
        {
            public function read(string $text, int $subjectId, iterable $politicians, iterable $issues): array
            {
                throw new \RuntimeException('model down');
            }

            public function method(): ?string
            {
                return null;
            }
        });

        $user = User::factory()->create();
        $record = $this->record($this->politician('Sari Wibowo'), 'Sari mendukung subsidi yang lebih besar bagi harga pangan rakyat.', PublicRecord::STATUS_PUBLISHED);

        $this->actingAs($user)
            ->post(route('admin.audits.records.run', $record))
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'failure',
                'message' => 'Inferensi gagal. model down',
            ]);

        $this->assertDatabaseHas('audits', [
            'public_record_id' => $record->id,
            'method' => null,
            'status' => Audit::STATUS_FAILED,
        ]);
    }

    public function test_editing_a_politician_is_audited_without_inference(): void
    {
        $user = User::factory()->create();
        $politician = $this->politician('Sari Wibowo');

        $this->actingAs($user)->post(route('admin.politicians.update', $politician), [
            'name' => 'Sari Baru',
            'position' => $politician->position,
            'institution' => $politician->institution,
            'party_id' => '',
            'status' => 1,
        ])->assertRedirect();

        $audit = Audit::query()
            ->where('action', Audit::ACTION_UPDATED)
            ->where('auditable_type', Politician::class)
            ->where('auditable_id', $politician->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('Sari Wibowo', $audit->changes['name']['from']);
        $this->assertSame('Sari Baru', $audit->changes['name']['to']);
        $this->assertSame(0, Audit::query()->where('action', Audit::ACTION_INFERENCE)->count());
    }

    private function politician(string $name): Politician
    {
        return Politician::create([
            'name' => $name,
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 1,
        ]);
    }

    private function record(Politician $politician, string $quote, int $status = PublicRecord::STATUS_PENDING): PublicRecord
    {
        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => $quote,
            'source_url' => 'https://example.com/audit-'.$politician->id.'-'.$status,
            'source_type' => 'Berita',
            'status' => $status,
        ]);
    }

    private function decision(Politician $politician, string $quote): array
    {
        return [
            'decision' => 'publish',
            'name' => $politician->name,
            'position' => $politician->position,
            'department' => $politician->institution,
            'party_id' => '',
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => $quote,
            'summary' => '',
            'source_type' => 'Berita',
            'source_url' => 'https://example.com/audit-'.$politician->id,
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

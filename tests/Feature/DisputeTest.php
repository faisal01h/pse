<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesTurnstile;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use FakesTurnstile, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dispute_form_is_a_public_menu_page(): void
    {
        $this->get('/dispute')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dispute'));

        $this->get('/pages/dispute')->assertNotFound();
    }

    public function test_a_published_record_can_be_disputed(): void
    {
        $this->fakeTurnstile('dispute');
        $record = $this->record(PublicRecord::STATUS_PUBLISHED);

        $this->post('/dispute', $this->withTurnstileToken([
            'target' => 'record',
            'public_record_id' => $record->id,
            'reason' => 'Kutipan ini tidak ada di transkrip resmi yang saya tautkan.',
            'source_url' => 'https://example.com/transkrip',
            'source_type' => 'transkrip',
        ]))->assertRedirect(route('dispute.received'));

        $receipt = $this->get(route('dispute.received'));
        $receipt->assertOk();
        $code = $receipt->original->getData()['page']['props']['code'];

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}(?:-[A-Z2-9]{4}){3}$/', $code);

        $dispute = Dispute::query()->first();
        $this->assertSame($code, $dispute->tracking_code);
        $this->assertStringNotContainsString('hash', json_encode($dispute->getAttributes()));

        $this->get(route('dispute.received'))
            ->assertInertia(fn ($page) => $page
                ->component('DisputeDiterima')
                ->where('code', null));

        $this->assertDatabaseHas('disputes', [
            'public_record_id' => $record->id,
            'status' => Dispute::STATUS_PENDING,
            'source_type' => 'transkrip',
            'tracking_code' => $code,
        ]);

        $this->get('/dispute/records?q=Sanggah')
            ->assertOk()
            ->assertJsonFragment(['id' => $record->id]);
    }

    public function test_unpublished_records_cannot_be_disputed(): void
    {
        $this->fakeTurnstile('dispute');
        $record = $this->record(PublicRecord::STATUS_PENDING);

        $this->post('/dispute', $this->withTurnstileToken([
            'target' => 'record',
            'public_record_id' => $record->id,
            'reason' => 'Kutipan ini tidak ada di transkrip resmi yang saya tautkan.',
            'source_url' => 'https://example.com/transkrip',
            'source_type' => 'transkrip',
        ]))->assertSessionHasErrors('public_record_id');

        $this->assertDatabaseCount('disputes', 0);

        $this->get('/dispute/records?q=Sanggah')->assertExactJson([]);
    }

    public function test_upholding_a_dispute_returns_the_record_to_review(): void
    {
        $record = $this->record(PublicRecord::STATUS_PUBLISHED);
        $dispute = Dispute::create([
            'public_record_id' => $record->id,
            'reason' => 'Kutipan ini tidak ada di transkrip resmi yang saya tautkan.',
            'source_url' => 'https://example.com/transkrip',
            'source_type' => 'transkrip',
            'status' => Dispute::STATUS_PENDING,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.disputes.decide', $dispute), ['decision' => 'uphold'])
            ->assertRedirect();

        $this->assertSame(Dispute::STATUS_UPHELD, $dispute->fresh()->status);
        $this->assertSame(PublicRecord::STATUS_PENDING, $record->fresh()->status);
    }

    public function test_pending_disputes_are_grouped_by_public_record(): void
    {
        $first = $this->record(PublicRecord::STATUS_PUBLISHED);
        $second = $this->record(PublicRecord::STATUS_PUBLISHED);
        Dispute::create($this->dispute($first->id, 'Kutipan pertama tidak sesuai dengan transkrip yang saya tautkan.'));
        Dispute::create($this->dispute($first->id, 'Kutipan pertama juga berbeda dari tanggal yang disebut sumber.'));
        Dispute::create($this->dispute($second->id, 'Kutipan kedua tidak ada pada sumber pembanding yang saya tautkan.'));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('disputes', 2)
                ->where('disputes.0.record.id', $second->id)
                ->has('disputes.0.disputes', 1)
                ->where('disputes.1.record.id', $first->id)
                ->has('disputes.1.disputes', 2)
                ->where('disputes.1.disputes.0.tracking_code', fn ($code) => is_string($code) && $code !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function dispute(int $recordId, string $reason): array
    {
        return [
            'public_record_id' => $recordId,
            'reason' => $reason,
            'source_url' => 'https://example.com/transkrip',
            'source_type' => 'transkrip',
            'status' => Dispute::STATUS_PENDING,
        ];
    }

    private function record(int $status): PublicRecord
    {
        $politician = Politician::create([
            'name' => 'Sanggah Uji',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 1,
        ]);

        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan yang sudah terbit untuk uji sanggahan.',
            'source_url' => 'https://example.com/arsip',
            'source_type' => 'Berita',
            'status' => $status,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Dispute;
use App\Models\Politician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesTurnstile;
use Tests\TestCase;

class AffiliationDisputeRetractTest extends TestCase
{
    use FakesTurnstile, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_ten_pending_disputes_auto_unpublish_affiliation_for_figure_and_business(): void
    {
        $this->fakeTurnstile('dispute');
        $affiliation = $this->publishedAffiliation();

        for ($i = 0; $i < 9; $i++) {
            $this->seedPendingDispute($affiliation->id, $i);
        }

        $this->assertSame(BusinessAffiliation::STATUS_PUBLISHED, (int) $affiliation->fresh()->status);
        $this->assertFalse($affiliation->fresh()->autoRetractIfThresholdMet());

        $this->postDispute($affiliation->id, 9);

        $affiliation->refresh();
        $this->assertSame(BusinessAffiliation::STATUS_PENDING, (int) $affiliation->status);
        $this->assertTrue($affiliation->dispute_retract_pending);
        $this->assertSame(10, (int) $affiliation->dispute_threshold);
        $this->assertSame(0, (int) $affiliation->politician->fresh()->status);
    }

    public function test_republish_after_retract_multiplies_threshold_by_five(): void
    {
        $user = User::factory()->create();
        $affiliation = $this->publishedAffiliation();
        $affiliation->fill([
            'status' => BusinessAffiliation::STATUS_PENDING,
            'dispute_retract_pending' => true,
            'dispute_threshold' => 10,
        ])->save();

        Dispute::create([
            'business_affiliation_id' => $affiliation->id,
            'reason' => str_repeat('Alasan sanggahan yang cukup panjang. ', 3),
            'source_url' => 'https://example.com/dismiss',
            'source_type' => 'berita',
            'status' => Dispute::STATUS_DISMISSED,
        ]);

        $this->actingAs($user)
            ->post(route('affiliations.decide', $affiliation), $this->decisionPayload($affiliation))
            ->assertRedirect(route('dashboard'));

        $affiliation->refresh();
        $this->assertSame(BusinessAffiliation::STATUS_PUBLISHED, (int) $affiliation->status);
        $this->assertFalse($affiliation->dispute_retract_pending);
        $this->assertSame(50, (int) $affiliation->dispute_threshold);
    }

    public function test_second_round_needs_fifty_pending_disputes(): void
    {
        $this->fakeTurnstile('dispute');
        $affiliation = $this->publishedAffiliation();
        $affiliation->update(['dispute_threshold' => 50]);

        for ($i = 0; $i < 49; $i++) {
            $this->seedPendingDispute($affiliation->id, $i);
        }

        $this->assertSame(BusinessAffiliation::STATUS_PUBLISHED, (int) $affiliation->fresh()->status);
        $this->assertFalse($affiliation->fresh()->autoRetractIfThresholdMet());

        $this->postDispute($affiliation->id, 49);
        $this->assertSame(BusinessAffiliation::STATUS_PENDING, (int) $affiliation->fresh()->status);
        $this->assertTrue($affiliation->fresh()->dispute_retract_pending);
    }

    private function publishedAffiliation(): BusinessAffiliation
    {
        $politician = Politician::create([
            'name' => 'Tokoh Sanggah',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
        $business = Business::create([
            'name' => 'PT Sanggah',
            'status' => Business::STATUS_PUBLISHED,
        ]);

        return BusinessAffiliation::create([
            'politician_id' => $politician->id,
            'business_id' => $business->id,
            'type' => BusinessAffiliation::TYPE_OWNERSHIP,
            'ownership_percent' => null,
            'summary' => 'Sumber menyebut tokoh memiliki usaha ini.',
            'source_url' => 'https://example.com/afiliasi-sanggah',
            'source_type' => 'berita',
            'status' => BusinessAffiliation::STATUS_PUBLISHED,
            'dispute_threshold' => 10,
            'dispute_retract_pending' => false,
        ]);
    }

    private function seedPendingDispute(int $affiliationId, int $index): void
    {
        Dispute::create([
            'business_affiliation_id' => $affiliationId,
            'reason' => 'Sanggahan nomor '.$index.' menyatakan afiliasi ini tidak sesuai sumber pembanding yang saya tautkan.',
            'source_url' => 'https://example.com/sanggah-'.$index,
            'source_type' => 'berita',
            'status' => Dispute::STATUS_PENDING,
        ]);
    }

    private function postDispute(int $affiliationId, int $index): void
    {
        $this->post('/dispute', $this->withTurnstileToken([
            'target' => 'affiliation',
            'business_affiliation_id' => $affiliationId,
            'reason' => 'Sanggahan nomor '.$index.' menyatakan afiliasi ini tidak sesuai sumber pembanding yang saya tautkan.',
            'source_url' => 'https://example.com/sanggah-'.$index,
            'source_type' => 'berita',
        ]))->assertRedirect(route('dispute.received'));
    }

    /**
     * @return array<string, mixed>
     */
    private function decisionPayload(BusinessAffiliation $affiliation): array
    {
        $affiliation->loadMissing(['politician', 'business']);

        return [
            'decision' => 'publish',
            'name' => $affiliation->politician->name,
            'position' => $affiliation->politician->position,
            'department' => $affiliation->politician->institution,
            'party_id' => '',
            'business_id' => (string) $affiliation->business_id,
            'type' => $affiliation->type,
            'ownership_unknown' => '1',
            'summary' => $affiliation->summary,
            'source_type' => $affiliation->source_type,
            'source_url' => $affiliation->source_url,
            'review_note' => str_repeat('Catatan pemeriksa setelah menolak sanggahan. ', 3),
            'check_source' => '1',
            'check_wording' => '1',
            'check_category' => '1',
            'check_duplicate' => '1',
            'check_legal_label' => '1',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Catalog\ReliabilityLevel;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\SeedsAuthorization;
use Tests\TestCase;

class ReliabilityLevelTest extends TestCase
{
    use RefreshDatabase, SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_publish_requires_reliability_level(): void
    {
        $user = $this->checker();
        $record = $this->pendingRecord();

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($record, [
                'reliability_level' => null,
            ]))
            ->assertSessionHasErrors('reliability_level');

        $this->assertSame(PublicRecord::STATUS_PENDING, (int) $record->fresh()->status);
    }

    public function test_publish_with_reliability_succeeds_and_appears_publicly(): void
    {
        $user = $this->checker();
        $record = $this->pendingRecord();

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($record, [
                'reliability_level' => ReliabilityLevel::OFFICIALLY_VERIFIED,
            ]))
            ->assertRedirect(route('dashboard'));

        $record->refresh();
        $this->assertSame(PublicRecord::STATUS_PUBLISHED, (int) $record->status);
        $this->assertSame(ReliabilityLevel::OFFICIALLY_VERIFIED, $record->reliability_level);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('reports', 1)
                ->where('reports.0.reliability_level', ReliabilityLevel::OFFICIALLY_VERIFIED));
    }

    public function test_reject_without_reliability_succeeds(): void
    {
        $user = $this->checker();
        $record = $this->pendingRecord();

        $payload = $this->decision($record, [
            'decision' => 'reject',
        ]);
        unset($payload['reliability_level']);

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $payload)
            ->assertRedirect(route('dashboard'));

        $this->assertSame(PublicRecord::STATUS_REJECTED, (int) $record->fresh()->status);
    }

    private function checker(): User
    {
        $this->seedAuthorization();
        $user = User::factory()->create();
        $role = Role::findOrCreate('pemeriksa', 'web');
        $role->givePermissionTo('checks.perform');
        $user->assignRole($role);

        return $user->fresh();
    }

    private function pendingRecord(): PublicRecord
    {
        $politician = Politician::create([
            'name' => 'Tokoh Uji',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 0,
        ]);

        return PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan uji keandalan.',
            'source_url' => 'https://example.com/reliability',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function decision(PublicRecord $record, array $overrides = []): array
    {
        $politician = $record->politician;

        return array_merge([
            'decision' => 'publish',
            'name' => $politician->name,
            'position' => $politician->position,
            'department' => $politician->institution,
            'party_id' => '',
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => $record->quote,
            'summary' => '',
            'source_type' => $record->source_type,
            'source_url' => $record->source_url,
            'review_note' => 'Sumber terbuka, kutipan sesuai, dan jenis catatan memang pernyataan publik.',
            'reliability_level' => ReliabilityLevel::HIGH,
            'check_source' => '1',
            'check_wording' => '1',
            'check_category' => '1',
            'check_duplicate' => '1',
            'check_legal_label' => '1',
        ], $overrides);
    }
}

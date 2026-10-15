<?php

namespace Tests\Feature;

use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_vetter_assigns_topics_and_the_public_record_shows_them(): void
    {
        $user = User::factory()->create();
        $politician = Politician::create([
            'name' => 'Topik Uji',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 0,
        ]);
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan tentang subsidi dan hutan.',
            'source_url' => 'https://example.com/topik',
            'source_type' => 'Berita',
            'status' => PublicRecord::STATUS_PENDING,
        ]);
        Topic::create(['name' => 'Lingkungan']);

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($politician, ['subsidi', 'lingkungan']))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(2, Topic::query()->count());
        $this->assertEqualsCanonicalizing(
            ['subsidi', 'Lingkungan'],
            $record->topics()->pluck('name')->all()
        );

        $this->get(route('politicians.show', $politician))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('politician.records.0.topics', fn ($topics) => collect($topics)->pluck('name')->sort()->values()->all() === ['Lingkungan', 'subsidi']));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reports.0.topics', fn ($topics) => collect($topics)->pluck('name')->sort()->values()->all() === ['Lingkungan', 'subsidi']));
    }

    public function test_clearing_topics_detaches_them_from_the_record(): void
    {
        $user = User::factory()->create();
        $politician = Politician::create([
            'name' => 'Topik Lepas',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 1,
        ]);
        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => 'Kutipan yang sempat punya topik.',
            'source_url' => 'https://example.com/lepas',
            'source_type' => 'Berita',
            'occurred_on' => '2026-01-01',
            'status' => PublicRecord::STATUS_PUBLISHED,
        ]);
        $record->assignTopics(['Energi']);

        $this->actingAs($user)
            ->post(route('reports.decide', $record), $this->decision($politician, []))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, $record->topics()->count());
        $this->assertSame(1, Topic::query()->count());
    }

    /**
     * @param  array<int, string>  $topics
     * @return array<string, mixed>
     */
    private function decision(Politician $politician, array $topics): array
    {
        return [
            'decision' => 'publish',
            'name' => $politician->name,
            'position' => $politician->position,
            'department' => $politician->institution,
            'party_id' => '',
            'category' => PublicRecord::CATEGORY_STATEMENT,
            'quote' => PublicRecord::query()->where('politician_id', $politician->id)->value('quote'),
            'summary' => '',
            'source_type' => 'Berita',
            'source_url' => PublicRecord::query()->where('politician_id', $politician->id)->value('source_url'),
            'review_note' => 'Sumber terbuka, kutipan sesuai, dan jenis catatan memang pernyataan publik.',
            'reliability_level' => 'high',
            'check_source' => '1',
            'check_wording' => '1',
            'check_category' => '1',
            'check_duplicate' => '1',
            'check_legal_label' => '1',
            'topics' => $topics,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Party;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoliticalRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_save_a_tendency(): void
    {
        $politician = $this->politician('Sari Wibowo');

        $this->post(route('admin.politicians.tendencies.store', $politician), $this->tendency())
            ->assertRedirect('/login');
    }

    public function test_admin_records_are_sourced_and_shown_only_when_the_other_profile_is_published(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['name' => 'Partai Contoh']);
        $otherParty = Party::create(['name' => 'Partai Lain']);
        $politician = $this->politician('Sari Wibowo', $party->id);
        $ally = $this->politician('Budi Santoso', $party->id);
        $hidden = Politician::create([
            'name' => 'Tidak Terbit',
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'status' => 0,
        ]);

        $note = 'Sumber menyebut dukung peran negara yang lebih besar pada harga pangan.';

        $this->actingAs($user)
            ->post(route('admin.politicians.tendencies.store', $politician), $this->tendency($note))
            ->assertRedirect(route('admin.politicians.edit', $politician));

        $this->actingAs($user)
            ->post(route('admin.politicians.links.store', $politician), [
                'other_id' => $ally->id,
                'type' => PoliticianLink::TYPE_ALLY,
                'summary' => 'Keduanya disebut satu koalisi dalam pemberitaan kampanye daerah itu.',
                'source_url' => 'https://example.com/sekutu',
                'source_type' => 'Berita',
            ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.politicians.links.store', $politician), [
                'other_id' => $hidden->id,
                'type' => PoliticianLink::TYPE_SUPPORT,
                'summary' => 'Sari disebut mendukung calon yang profilnya belum diterbitkan ke publik.',
                'source_url' => 'https://example.com/dukung',
                'source_type' => 'Berita',
            ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.politicians.stances.store', $politician), [
                'issue_name' => 'Subsidi pangan',
                'position' => 'dukung',
                'note' => $note,
                'source_url' => 'https://example.com/pangan',
                'source_type' => 'Pidato',
            ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.politicians.stances.store', $ally), [
                'issue_name' => 'subsidi pangan',
                'position' => 'dukung',
                'note' => $note,
                'source_url' => 'https://example.com/pangan-budi',
                'source_type' => 'Pidato',
            ])->assertRedirect();

        $this->assertSame(1, Issue::query()->count());
        $link = PoliticianLink::query()->where('type', PoliticianLink::TYPE_ALLY)->first();
        $this->assertSame(min($politician->id, $ally->id), $link->politician_a_id);
        $this->assertSame(max($politician->id, $ally->id), $link->politician_b_id);

        $this->actingAs($user)
            ->post(route('admin.politicians.links.store', $politician), [
                'other_id' => $politician->id,
                'type' => PoliticianLink::TYPE_RIVAL,
                'summary' => 'Ringkasan ini cukup panjang untuk melewati batas minimum karakter.',
                'source_url' => 'https://example.com/diri',
                'source_type' => 'Berita',
            ])->assertSessionHasErrors('other_id');

        $this->actingAs($user)
            ->post(route('admin.politicians.tendencies.store', $politician), [
                ...$this->tendency(),
                'source_url' => 'http://example.com/tidak-aman',
            ])->assertSessionHasErrors('source_url');

        $this->actingAs($user)
            ->post(route('admin.parties.tendencies.store', $party), $this->tendency('Partai disebut berpihak pada peran negara dalam dokumen kongres.'))
            ->assertRedirect(route('admin.parties.edit', $party));

        $this->actingAs($user)
            ->post(route('admin.parties.tendencies.store', $otherParty), $this->tendency('Partai lain juga disebut berpihak pada peran negara dalam kongresnya.'))
            ->assertRedirect(route('admin.parties.edit', $otherParty));

        $this->actingAs($user)
            ->post(route('admin.parties.links.store', $party), [
                'other_id' => $otherParty->id,
                'type' => PartyLink::TYPE_COALITION,
                'summary' => 'Kedua partai disebut berada dalam koalisi yang sama pada pemilu tersebut.',
                'source_url' => 'https://example.com/koalisi',
                'source_type' => 'Berita',
            ])->assertRedirect();

        $this->get(route('politicians.show', $politician))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Politician')
                ->has('politician.tendencies', 1)
                ->where('politician.tendencies.0.score', 0)
                ->where('politician.tendencies.0.left', 'Peran negara besar')
                ->where('politician.tendencies.0.right', 'Orientasi pasar')
                ->has('politician.connections', 1)
                ->where('politician.connections.0.other.name', 'Budi Santoso')
                ->where('politician.issue_stances.0.issue', 'Subsidi pangan')
                ->where('politician.issue_stances.0.aligned.0.name', 'Budi Santoso'));

        $this->get(route('parties.show', $party))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Party')
                ->where('party.name', 'Partai Contoh')
                ->has('party.tendencies', 1)
                ->where('party.connections.0.other.name', 'Partai Lain')
                ->has('politicians', 2));
    }

    private function politician(string $name, ?int $partyId = null): Politician
    {
        $politician = Politician::create([
            'name' => $name,
            'position' => 'Anggota DPR',
            'institution' => 'DPR RI',
            'party_id' => $partyId,
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

    private function tendency(?string $note = null): array
    {
        return [
            'axis' => 'ekonomi',
            'score' => 0,
            'note' => $note ?? 'Sumber menyebut dukung peran negara yang lebih besar pada harga pangan.',
            'source_url' => 'https://example.com/ekonomi',
            'source_type' => 'Pidato',
        ];
    }
}

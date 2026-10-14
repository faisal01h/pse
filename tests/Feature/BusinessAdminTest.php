<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_list_businesses(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.businesses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Businesses'));

        $this->actingAs($admin)
            ->post(route('admin.businesses.store'), [
                'name' => 'PT Contoh',
                'sector' => 'Energi',
                'status' => Business::STATUS_PUBLISHED,
            ])
            ->assertRedirect();

        $business = Business::query()->first();
        $this->assertSame('PT Contoh', $business->name);
        $this->assertSame(Business::STATUS_PUBLISHED, (int) $business->status);

        $this->actingAs($admin)
            ->post(route('admin.businesses.update', $business), [
                'name' => 'PT Contoh Diperbarui',
                'sector' => 'Pertambangan',
                'status' => Business::STATUS_DRAFT,
            ])
            ->assertRedirect(route('admin.businesses.edit', $business));

        $business->refresh();
        $this->assertSame('PT Contoh Diperbarui', $business->name);
        $this->assertSame(Business::STATUS_DRAFT, (int) $business->status);
    }

    public function test_cannot_delete_business_with_affiliations(): void
    {
        $admin = $this->makeAdmin();
        $politician = Politician::create([
            'name' => 'Tokoh',
            'position' => 'Anggota',
            'institution' => 'DPR',
            'status' => 1,
        ]);
        $business = Business::create([
            'name' => 'PT Terikat',
            'status' => Business::STATUS_PUBLISHED,
        ]);
        BusinessAffiliation::create([
            'politician_id' => $politician->id,
            'business_id' => $business->id,
            'type' => BusinessAffiliation::TYPE_OWNERSHIP,
            'summary' => 'Sumber menyebut tokoh memiliki usaha ini.',
            'source_url' => 'https://example.com/a',
            'source_type' => 'berita',
            'status' => BusinessAffiliation::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.businesses.edit', $business))
            ->delete(route('admin.businesses.destroy', $business))
            ->assertRedirect(route('admin.businesses.edit', $business))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
    }

    public function test_business_routes_require_permissions(): void
    {
        $user = $this->makePlainUser();

        $this->actingAs($user)->get(route('admin.businesses.index'))->assertForbidden();

        $user->givePermissionTo('businesses.edit');
        $this->actingAs($user->fresh())->get(route('admin.businesses.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.businesses.create'))->assertForbidden();
    }
}

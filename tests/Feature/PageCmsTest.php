<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_pages_are_public(): void
    {
        $this->get('/pages/legal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Page')
                ->where('page.title', 'Legal')
                ->where('page.html', fn ($html) => str_contains($html, 'informasi hukum')));

        $this->get('/pages/terms')->assertOk()->assertInertia(fn ($page) => $page->where('page.title', 'Ketentuan Layanan'));
        $this->get('/pages/privacy')->assertOk()->assertInertia(fn ($page) => $page->where('page.title', 'Kebijakan Privasi'));
    }

    public function test_missing_pages_use_the_public_not_found_screen(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('NotFound'));
    }

    public function test_markdown_does_not_keep_script_tags(): void
    {
        Page::query()->where('slug', 'legal')->update([
            'format' => Page::FORMAT_MARKDOWN,
            'body' => "Aman\n\n<script>alert(1)</script>\n\n[sumber](javascript:alert(1))",
        ]);

        $this->get('/pages/legal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('page.html', function ($html) {
                return str_contains($html, 'Aman')
                    && ! str_contains($html, '<script')
                    && ! str_contains($html, 'javascript:');
            }));
    }

    public function test_guest_cannot_manage_pages(): void
    {
        $this->get('/admin/pages')->assertRedirect('/login');
        $this->post('/admin/pages', [
            'title' => 'Catatan',
            'slug' => 'catatan',
            'format' => 'markdown',
            'body' => 'Isi',
        ])->assertRedirect('/login');
    }

    public function test_admin_can_edit_a_footer_page_and_cannot_delete_it(): void
    {
        $user = User::factory()->create();
        $legal = Page::query()->where('slug', 'legal')->firstOrFail();

        $this->actingAs($user)->post(route('admin.pages.update', $legal), [
            'title' => 'Legal',
            'slug' => 'diubah',
            'format' => 'richtext',
            'body' => '<p>Teks baru</p><script>alert(1)</script><a href="javascript:alert(1)">buruk</a>',
        ])->assertRedirect(route('admin.pages.edit', $legal));

        $legal->refresh();
        $this->assertSame('legal', $legal->slug);
        $this->assertSame(Page::FORMAT_RICHTEXT, $legal->format);
        $this->assertStringContainsString('Teks baru', $legal->body);
        $this->assertStringNotContainsString('<script', $legal->body);
        $this->assertStringNotContainsString('javascript:', $legal->body);

        $this->actingAs($user)->delete(route('admin.pages.destroy', $legal))->assertRedirect(route('admin.pages.edit', $legal));
        $this->assertDatabaseHas('pages', ['id' => $legal->id]);
    }

    public function test_admin_can_create_and_delete_an_extra_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/pages', [
            'title' => 'Tentang',
            'slug' => 'about',
            'format' => 'markdown',
            'body' => '## Tentang\n\nIsi halaman.',
        ])->assertRedirect();

        $page = Page::query()->where('slug', 'about')->firstOrFail();
        $this->assertFalse($page->pinned);

        $this->get('/pages/about')
            ->assertOk()
            ->assertInertia(fn ($view) => $view->where('page.html', fn ($html) => str_contains($html, 'Tentang') && str_contains($html, '<h2')));

        $this->actingAs($user)->delete(route('admin.pages.destroy', $page))->assertRedirect(route('admin.pages.index'));
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }
}

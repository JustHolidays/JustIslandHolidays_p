<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Island;
use App\Models\Page;
use App\Models\User;
use App\Support\FooterLinks;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Settings › Footer links (21 Nov 2026). */
class FooterLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalPagesSeeder::class);
    }

    /** Footer link labels in order. @return list<string> */
    private function footer(): array
    {
        preg_match_all('#<a href="[^"]*"[^>]*data-footer-link>([^<]+)</a>#', $this->get('/en')->assertOk()->getContent(), $m);

        return $m[1];
    }

    public function test_the_standard_links_show_until_changed(): void
    {
        $this->assertSame(['About us', 'Contact', 'Terms', 'Privacy', 'Accessibility', 'Our partners', 'For owners', 'Owner sign in'], $this->footer());
    }

    public function test_super_admins_relabel_reorder_remove_and_add_links(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('admin.footer-links.edit'))->assertOk()->assertSee('data-footer-links-form', false)->assertSee('These are the standard links.');

        $rows = [];
        foreach (FooterLinks::defaults() as $i => $link) {
            $rows[$i] = ['goes_to' => $link['type'].':'.$link['target'], 'label' => ['en' => $link['label']['en']], 'position' => $i + 1];
        }
        $rows[0]['label']['en'] = 'Who we are';       // relabel About us
        $rows[0]['position'] = 9;                     // and move it to the end
        $rows[2]['remove'] = 1;                       // remove Terms
        $rows[8] = ['goes_to' => 'url', 'url' => 'https://blog.example.com', 'label' => ['en' => 'Our blog'], 'position' => 2];
        $rows[9] = ['goes_to' => '', 'label' => ['en' => ''], 'position' => 10];   // a spare row left empty

        $this->actingAs($admin)->put(route('admin.footer-links.update'), ['links' => $rows])
            ->assertRedirect(route('admin.footer-links.edit'))->assertSessionHasNoErrors();

        auth()->logout();
        $this->assertSame(['Contact', 'Our blog', 'Privacy', 'Accessibility', 'Our partners', 'For owners', 'Owner sign in', 'Who we are'], $this->footer());
        $this->get('/en')->assertSee('href="https://blog.example.com" class=', false)->assertSee('rel="noopener"', false);
        $this->assertTrue(AuditLog::where('action', 'site.footer_links')->exists());
    }

    public function test_unpublished_or_missing_pages_are_left_out(): void
    {
        Page::where('slug', 'contact')->update(['is_published' => false]);
        $this->assertNotContains('Contact', $this->footer());

        FooterLinks::save([['type' => 'page', 'target' => 'gone', 'label' => ['en' => 'Gone']], ['type' => 'builtin', 'target' => 'search', 'label' => ['en' => 'Search']]], null);
        $this->assertSame(['Search'], $this->footer());
    }

    public function test_mistakes_are_explained_and_nothing_is_saved(): void
    {
        $admin = $this->superAdmin();
        $cases = [
            [['goes_to' => 'url', 'url' => 'http://insecure.example', 'label' => ['en' => 'Bad']], 'links.0.url'],
            [['goes_to' => 'url', 'url' => 'javascript:alert(1)', 'label' => ['en' => 'Bad']], 'links.0.url'],
            [['goes_to' => 'page:not-a-page', 'label' => ['en' => 'Nope']], 'links.0.goes_to'],
            [['goes_to' => 'builtin:admin', 'label' => ['en' => 'Admin']], 'links.0.goes_to'],
            [['goes_to' => 'page:about', 'label' => ['en' => '']], 'links.0.label.en'],
            [['goes_to' => '', 'label' => ['en' => 'Lonely label']], 'links.0.goes_to'],
        ];
        foreach ($cases as [$row, $field]) {
            $this->actingAs($admin)->from(route('admin.footer-links.edit'))->put(route('admin.footer-links.update'), ['links' => [$row]])
                ->assertSessionHasErrors($field);
        }
        $this->actingAs($admin)->put(route('admin.footer-links.update'), ['links' => [['goes_to' => '', 'label' => ['en' => '']]]])->assertSessionHasErrors('links');
        $this->assertFalse(FooterLinks::isCustomised());
    }

    public function test_using_the_standard_links_again(): void
    {
        $admin = $this->superAdmin();
        FooterLinks::save([['type' => 'builtin', 'target' => 'search', 'label' => ['en' => 'Search']]], $admin->id);

        $this->actingAs($admin)->put(route('admin.footer-links.update'), ['reset' => 1])->assertSessionHas('status', 'The footer has the standard links again.');
        auth()->logout();
        $this->assertCount(8, $this->footer());
    }

    public function test_only_super_admins_can_open_it(): void
    {
        $islandAdmin = $this->islandAdmin(Island::factory()->create());
        foreach ([$islandAdmin, User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.footer-links.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.footer-links.update'), ['reset' => 1])->assertForbidden();
        }
    }
}

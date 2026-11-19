<?php

namespace Tests\Feature\Admin;

use App\Models\Island;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The admin menu order and its Locations and Settings headings (set 19 Nov 2026). */
class AdminMenuOrderTest extends TestCase
{
    use RefreshDatabase;

    /** Menu labels and headings, in the order they appear. */
    private function menu(string $html): array
    {
        $start = strpos($html, '<nav aria-label="Admin"');
        $nav = substr($html, $start, strpos($html, '</nav>', $start) - $start);
        preg_match_all('#<li class="w-full px-3[^"]*" data-menu-heading>([^<]+)</li>|<a href="[^"]*/admin(?:/(?:plans|listings|licences|enquiries|users|ads|clicks|locations/create|staff|pages|property-types|appearance|admin-colours|audit))?"[^>]*>\s*([^<]+?)\s*</a>|<summary[^>]*>\s*([^<]+?)\s*<span#', $nav, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => isset($x[3]) && $x[3] !== '' ? $x[3] : (($x[2] ?? '') !== '' ? $x[2] : 'HEADING: '.$x[1]), $m);
    }

    public function test_super_admin_menu_is_in_the_chosen_order(): void
    {
        $this->assertSame([
            'Dashboard', 'Plans', 'Listing Search', 'Licence checks', 'Enquiries', 'Owners', 'Agents', 'Affiliates', 'Affiliate Adverts', 'Click reports',
            'HEADING: Locations', 'Islands and resorts', 'Add a location',
            'HEADING: Settings', 'Staff', 'Content pages', 'Property types', 'Frontend Styler', 'Admin Styler', 'Audit log',
        ], $this->menu($this->actingAs($this->superAdmin())->get('/admin')->assertOk()->getContent()));
    }

    public function test_island_admin_sees_only_their_items_and_headings_with_something_under_them(): void
    {
        $this->assertSame([
            'Dashboard', 'Listing Search', 'Licence checks', 'Enquiries', 'Owners', 'Agents', 'Click reports',
            'HEADING: Locations', 'Islands and resorts',
            'HEADING: Settings', 'Content pages',
        ], $this->menu($this->actingAs($this->islandAdmin(Island::factory()->create()))->get('/admin')->assertOk()->getContent()));
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Commission reports are not needed (19 Nov 2026): no commission figures in the admin. */
class NoCommissionReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_admin_page_shows_commission(): void
    {
        $admin = $this->superAdmin();
        $affiliate = User::factory()->affiliate()->create();

        foreach (['/admin', '/admin/affiliates', "/admin/affiliates/{$affiliate->id}", '/admin/clicks'] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsStringIgnoringCase('commission pending', $html, $url);
            $this->assertStringNotContainsString('No commission reports', $html, $url);
            $this->assertStringNotContainsString('Bookings matched to a click', $html, $url);
        }
    }
}

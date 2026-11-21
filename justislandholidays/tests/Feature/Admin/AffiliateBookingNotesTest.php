<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Booking Notes for affiliates (21 Nov 2026): written in admin, shown under Check availability. */
class AffiliateBookingNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->partner = User::factory()->affiliate()->create(['name' => 'Sunny Stays']);
        $this->listing = Listing::factory()->live()->titled('Villa Sol')->withDeeplink()->create(['owner_id' => $this->partner->id])->load(['island', 'area']);
    }

    public function test_super_admins_write_the_notes_on_the_affiliate_page(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.affiliates.show', $this->partner))->assertOk()
            ->assertSee('data-booking-notes-form', false)->assertSee('name="notes[en]"', false);

        $this->actingAs($admin)->post(route('admin.affiliates.booking-notes', $this->partner), ['notes' => ['en' => "A **£200** deposit secures the booking.\n\n- Linen included\n- Pool heating extra"]])
            ->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Booking Notes saved'));

        $this->assertSame(['en' => "A **£200** deposit secures the booking.\n\n- Linen included\n- Pool heating extra"], $this->partner->fresh()->affiliate_booking_notes);
        $this->assertTrue(AuditLog::where('action', 'affiliate.booking_notes_update')->exists());
        $this->actingAs($admin)->get(route('admin.affiliates.show', $this->partner))->assertSee('How it looks to visitors')->assertSee('<strong>£200</strong>', false);
    }

    public function test_visitors_see_them_between_check_availability_and_the_adverts(): void
    {
        $this->partner->forceFill(['affiliate_booking_notes' => ['en' => "Deposit **£200**.\n\n<script>alert(1)</script>\n\n[Terms](https://sunny.example/terms) [bad](javascript:alert(1))"]])->save();

        $html = $this->get($this->listing->publicUrl('en'))->assertOk()->getContent();

        $this->assertStringContainsString('<h2 id="booking-notes-heading" class="text-xl font-semibold">Booking Notes</h2>', $html);
        $this->assertStringContainsString('<strong>£200</strong>', $html);
        $this->assertStringContainsString('href="https://sunny.example/terms"', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);

        $button = strpos($html, 'data-link-type=');
        $notes = strpos($html, 'data-booking-notes');
        $this->assertGreaterThan($button, $notes, 'Below the Check availability button.');
        $adverts = strpos($html, 'data-advert-section');
        $this->assertNotFalse($adverts, 'The sidebar advert section is on the page.');
        $this->assertLessThan($adverts, $notes, 'Above the adverts.');
    }

    public function test_nothing_shows_without_notes_or_on_owner_pages(): void
    {
        $this->get($this->listing->publicUrl('en'))->assertOk()->assertDontSee('data-booking-notes', false)->assertDontSee('Booking Notes');

        $owner = User::factory()->owner()->create(['affiliate_booking_notes' => null]);
        $owned = Listing::factory()->live()->titled('Casa Owner')->create(['owner_id' => $owner->id])->load(['island', 'area']);
        $owner->forceFill(['affiliate_booking_notes' => ['en' => 'Should never show']])->save();
        $this->get($owned->publicUrl('en'))->assertOk()->assertDontSee('Should never show');
    }

    public function test_emptying_the_box_removes_them(): void
    {
        $admin = $this->superAdmin();
        $this->partner->forceFill(['affiliate_booking_notes' => ['en' => 'Old notes']])->save();

        $this->actingAs($admin)->post(route('admin.affiliates.booking-notes', $this->partner), ['notes' => ['en' => '   ']])
            ->assertSessionHas('status', 'Booking Notes removed.');
        $this->assertNull($this->partner->fresh()->affiliate_booking_notes);
    }

    public function test_long_notes_are_refused_and_only_super_admins_can_save(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.affiliates.booking-notes', $this->partner), ['notes' => ['en' => str_repeat('a', 2001)]])
            ->assertSessionHasErrors('notes.en');

        $islandAdmin = $this->islandAdmin(Island::factory()->create());
        foreach ([$islandAdmin, $this->partner, User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->post(route('admin.affiliates.booking-notes', $this->partner), ['notes' => ['en' => 'Hijack']])->assertForbidden();
        }
        $this->assertNull($this->partner->fresh()->affiliate_booking_notes);

        $owner = User::factory()->owner()->create();
        $this->actingAs($admin)->post(route('admin.affiliates.booking-notes', $owner), ['notes' => ['en' => 'x']])->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Account;

use App\Models\DeeplinkClick;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnquiryInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_their_enquiries(): void
    {
        $mine = Enquiry::factory()->create(['guest_name' => 'Mine Guest']);
        Enquiry::factory()->create(['guest_name' => 'Other Guest']);

        $this->actingAs($mine->owner)->get('/account/enquiries')->assertOk()
            ->assertSee('Mine Guest')
            ->assertDontSee('Other Guest');
    }

    public function test_spam_is_hidden_by_default(): void
    {
        $spam = Enquiry::factory()->create(['guest_name' => 'Spammy']);
        $spam->forceFill(['status' => 'spam'])->save();

        $this->actingAs($spam->owner)->get('/account/enquiries')->assertDontSee('Spammy');
        $this->actingAs($spam->owner)->get('/account/enquiries?status=spam')->assertSee('Spammy');
    }

    public function test_owner_reads_and_marks_an_enquiry(): void
    {
        $enquiry = Enquiry::factory()->create(['message' => 'Is it free in June?']);

        $this->actingAs($enquiry->owner)->get("/account/enquiries/{$enquiry->id}")->assertOk()->assertSee('Is it free in June?');
        $this->actingAs($enquiry->owner)->patch("/account/enquiries/{$enquiry->id}/status", ['status' => 'replied'])->assertRedirect();

        $this->assertSame('replied', $enquiry->fresh()->status);
    }

    public function test_others_cannot_read_or_mark_it(): void
    {
        $enquiry = Enquiry::factory()->create();
        $stranger = User::factory()->owner()->create();

        $this->actingAs($stranger)->get("/account/enquiries/{$enquiry->id}")->assertForbidden();
        $this->actingAs($stranger)->patch("/account/enquiries/{$enquiry->id}/status", ['status' => 'closed'])->assertForbidden();
        $this->assertSame('new', $enquiry->fresh()->status);
    }

    public function test_invalid_status_is_refused(): void
    {
        $enquiry = Enquiry::factory()->create();

        $this->actingAs($enquiry->owner)->patch("/account/enquiries/{$enquiry->id}/status", ['status' => 'deleted'])->assertSessionHasErrors('status');
    }

    public function test_click_report_counts_human_clicks_on_own_listings(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = Listing::factory()->live()->titled('Partner villa')->withDeeplink()->create(['owner_id' => $affiliate->id]);
        $click = fn (bool $bot, bool $unique) => DeeplinkClick::create([
            'click_id' => (string) Str::ulid(), 'listing_id' => $listing->id, 'affiliate_id' => $affiliate->id,
            'island_id' => $listing->island_id, 'clicked_at' => now(), 'lang' => 'en', 'source_page' => 'listing',
            'ip_hash' => str_repeat('a', 64), 'is_bot' => $bot, 'is_unique' => $unique, 'target_url' => 'https://x.example',
        ]);
        $click(false, true);
        $click(false, false);
        $click(true, false);

        $this->actingAs($affiliate)->get('/account/clicks')->assertOk()
            ->assertSee('Partner villa')
            ->assertSeeInOrder(['Partner villa', '2', '1']);

        $this->actingAs(User::factory()->owner()->create())->get('/account/clicks')->assertOk()->assertDontSee('Partner villa');
    }
}

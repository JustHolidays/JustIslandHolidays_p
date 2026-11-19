<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus as S;
use App\Models\AuditLog;
use App\Models\Enquiry;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\AccountNotice;
use App\Support\Fetch\SafeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class LicenceDeeplinkEnquiryTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private Island $island;

    private Island $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Notification::fake();
        $this->island = Island::factory()->create();
        $this->other = Island::factory()->create();
        $this->admin = $this->islandAdmin($this->island);
        $this->app->instance(SafeFetcher::class, new SafeFetcher(fn () => ['93.184.216.34']));
    }

    public function test_licence_queue_lists_pending_owner_licences_on_own_islands(): void
    {
        $mine = $this->completeListing(island: $this->island);
        $theirs = $this->completeListing(island: $this->other);

        $this->actingAs($this->admin)->get('/admin/licences')->assertOk()
            ->assertSee($mine->licence->registration_number)
            ->assertSee(route('admin.listings.show', $mine), false)
            ->assertDontSee(route('admin.listings.show', $theirs), false);
    }

    public function test_verify_records_who_and_when(): void
    {
        $listing = $this->completeListing(island: $this->island);

        $this->actingAs($this->admin)->post("/admin/licences/{$listing->id}/verify")->assertSessionHasNoErrors();

        $licence = $listing->fresh()->licence;
        $this->assertSame('verified', $licence->status);
        $this->assertSame($this->admin->id, $licence->verified_by);
        $this->assertNotNull($licence->verified_at);
        $this->assertSame(1, AuditLog::where('action', 'licence.verify')->count());
    }

    public function test_reject_needs_a_note_and_tells_the_lister(): void
    {
        $listing = $this->completeListing(island: $this->island);

        $this->actingAs($this->admin)->post("/admin/licences/{$listing->id}/reject", ['notes' => ''])->assertSessionHasErrors('notes');
        $this->actingAs($this->admin)->post("/admin/licences/{$listing->id}/reject", ['notes' => 'Number not in the register'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $listing->fresh()->licence->status);
        Notification::assertSentTo($listing->owner, AccountNotice::class, fn ($n) => $n->kind === 'licence.rejected');
    }

    public function test_other_island_licences_are_refused(): void
    {
        $theirs = $this->completeListing(island: $this->other);

        $this->actingAs($this->admin)->post("/admin/licences/{$theirs->id}/verify")->assertForbidden();
        $this->actingAs($this->admin)->get("/admin/licences/{$theirs->id}/document")->assertForbidden();
        $this->assertSame('pending', $theirs->fresh()->licence->status);
    }

    public function test_document_download_is_audited(): void
    {
        Storage::fake('local');
        $listing = $this->completeListing(island: $this->island);
        $path = UploadedFile::fake()->create('l.pdf', 10, 'application/pdf')->store('licences', 'local');
        $listing->licence->forceFill(['document_path' => $path])->save();

        $this->actingAs($this->admin)->get("/admin/licences/{$listing->id}/document")->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'licence.document_view')->count());
    }

    /** A live affiliate listing with a link (only affiliate links are ever checked). */
    private function affiliateLinked(array $attributes): Listing
    {
        return $this->setStatus($this->completeListing(User::factory()->affiliate()->create(), $this->island, $attributes), S::Live);
    }

    public function test_recheck_marks_links_working_or_broken(): void
    {
        $ok = $this->affiliateLinked(['deeplink_url' => 'https://good.example/1']);
        $bad = $this->affiliateLinked(['deeplink_url' => 'https://bad.example/1']);
        Http::fake(['good.example/*' => Http::response('ok', 200), 'bad.example/*' => Http::response('gone', 404)]);

        $this->actingAs($this->admin)->post("/admin/deeplinks/{$ok->id}/recheck");
        $this->actingAs($this->admin)->post("/admin/deeplinks/{$bad->id}/recheck");

        $this->assertSame('ok', $ok->fresh()->deeplink_status);
        $this->assertSame('broken', $bad->fresh()->deeplink_status);
        $this->assertNotNull($bad->fresh()->deeplink_checked_at);
    }

    public function test_switch_off_hides_the_banner(): void
    {
        $listing = $this->affiliateLinked(['deeplink_url' => 'https://good.example/1', 'deeplink_status' => 'ok']);

        $this->actingAs($this->admin)->post("/admin/deeplinks/{$listing->id}/switch-off");

        $this->assertFalse($listing->fresh()->showsCheckAvailability());
    }

    public function test_owner_and_agent_links_are_never_checked_or_switched_off(): void
    {
        Http::fake();
        $owner = $this->setStatus($this->completeListing(island: $this->island, attributes: ['deeplink_url' => 'https://own.example/1']), S::Live);
        $agent = $this->setStatus($this->completeListing(User::factory()->agent()->create(), $this->island, ['deeplink_url' => 'https://agent.example/1']), S::Live);

        foreach ([$owner, $agent] as $listing) {
            $this->actingAs($this->admin)->post("/admin/deeplinks/{$listing->id}/recheck")->assertNotFound();
            $this->actingAs($this->admin)->post("/admin/deeplinks/{$listing->id}/switch-off")->assertNotFound();
            $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/check-link")->assertNotFound();
            $this->assertTrue($listing->fresh()->showsCheckAvailability());
        }
        Http::assertNothingSent();

        // The old page for them is gone.
        $this->actingAs($this->admin)->get('/admin/deeplinks')->assertNotFound();
    }

    public function test_an_old_broken_result_does_not_hide_an_owners_banner(): void
    {
        $owner = $this->setStatus($this->completeListing(island: $this->island, attributes: ['deeplink_url' => 'https://own.example/1', 'deeplink_status' => 'broken']), S::Live);
        $affiliate = $this->affiliateLinked(['deeplink_url' => 'https://partner.example/1', 'deeplink_status' => 'broken']);

        $this->assertTrue($owner->showsCheckAvailability());
        $this->assertSame('Lister looks after it', $owner->deeplinkStatusLabel());
        $this->assertFalse($affiliate->showsCheckAvailability());
    }

    public function test_monitor_lists_broken_first_and_flags_hosts_not_on_the_allowed_list(): void
    {
        $partner = User::factory()->affiliate()->create();
        $this->setStatus($this->completeListing($partner, $this->island, ['deeplink_url' => 'https://partner.example/1', 'deeplink_status' => 'ok']), S::Live);
        $this->setStatus($this->completeListing($partner, $this->island, ['deeplink_url' => 'https://other.example/1', 'deeplink_status' => 'broken']), S::Live);
        $super = $this->superAdmin();

        $this->actingAs($super)->post('/admin/deeplinks/hosts', ['hosts' => "partner.example\nnot a host"])->assertSessionHasNoErrors();
        $this->assertSame(['partner.example'], \App\Listings\DeeplinkChecker::allowedHosts());

        $this->actingAs($super)->get(route('admin.affiliates.deeplinks', $partner))->assertOk()
            ->assertSeeInOrder(['other.example', 'partner.example'])
            ->assertSee('Not on the allowed list');

        $this->actingAs($this->admin)->post('/admin/deeplinks/hosts', ['hosts' => ''])->assertForbidden();
    }

    public function test_message_to_lister(): void
    {
        $listing = $this->setStatus($this->completeListing(island: $this->island, attributes: ['deeplink_url' => 'https://good.example/1']), S::Live);

        $this->actingAs($this->admin)->post("/admin/deeplinks/{$listing->id}/message", ['message' => 'Your link opens the wrong villa.']);

        Notification::assertSentTo($listing->owner, AccountNotice::class, fn ($n) => $n->kind === 'deeplink.message' && $n->reason === 'Your link opens the wrong villa.');
    }

    public function test_enquiries_are_scoped_flag_recent_and_opening_one_is_audited(): void
    {
        $mine = Enquiry::factory()->for(Listing::factory()->onIsland($this->island))->create(['guest_name' => 'Recent Guest']);
        $old = Enquiry::factory()->for(Listing::factory()->onIsland($this->island))->create(['guest_name' => 'Old Guest']);
        $old->forceFill(['created_at' => now()->subDays(30)])->save();
        $theirs = Enquiry::factory()->for(Listing::factory()->onIsland($this->other))->create(['guest_name' => 'Elsewhere Guest']);

        $this->actingAs($this->admin)->get('/admin/enquiries')->assertOk()
            ->assertSeeInOrder(['Recent Guest', 'Old Guest'])
            ->assertSee('>Recent</span>', false)
            ->assertDontSee('Elsewhere Guest');

        $this->actingAs($this->admin)->get("/admin/enquiries/{$mine->id}")->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'enquiry.view')->where('actor_id', $this->admin->id)->count());

        $this->actingAs($this->admin)->get("/admin/enquiries/{$theirs->id}")->assertForbidden();
    }

    public function test_admin_marks_an_enquiry_as_spam(): void
    {
        $enquiry = Enquiry::factory()->for(Listing::factory()->onIsland($this->island))->create();

        $this->actingAs($this->admin)->post("/admin/enquiries/{$enquiry->id}/spam");

        $this->assertSame('spam', $enquiry->fresh()->status);
    }
}

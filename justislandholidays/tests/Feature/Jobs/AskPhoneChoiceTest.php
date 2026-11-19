<?php

namespace Tests\Feature\Jobs;

use App\Models\Listing;
use App\Models\ListingPrivate;
use App\Models\User;
use App\Notifications\AccountNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class AskPhoneChoiceTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private function home(User $owner, string $plan, ?string $phone, bool $show = false): Listing
    {
        $listing = Listing::factory()->live()->titled('Home '.uniqid())->create(['owner_id' => $owner->id, 'plan_code' => $plan]);
        $private = new ListingPrivate(['lat' => 39.9, 'lng' => 4.1, 'contact_email' => 'x@example.com', 'contact_phone' => $phone, 'show_phone' => $show]);
        $private->listing_id = $listing->id;
        $private->save();

        return $listing;
    }

    public function test_emails_each_paid_owner_with_a_hidden_phone_once(): void
    {
        $this->seedPlans();
        Notification::fake();
        $paid = User::factory()->owner()->create();
        $this->home($paid, 'standard', '+34 600 000 001');
        $this->home($paid, 'featured', '+34 600 000 002');
        $free = User::factory()->owner()->create();
        $this->home($free, 'free', '+34 600 000 003');
        $chose = User::factory()->owner()->create();
        $this->home($chose, 'standard', '+34 600 000 004', true);
        $noPhone = User::factory()->owner()->create();
        $this->home($noPhone, 'standard', null);

        $this->artisan('listings:ask-phone-choice --dry-run')->expectsOutputToContain('Would email 1')->assertSuccessful();
        Notification::assertNothingSent();

        $this->artisan('listings:ask-phone-choice')->assertSuccessful();

        Notification::assertSentToTimes($paid, AccountNotice::class, 1);
        Notification::assertNotSentTo([$free, $chose, $noPhone], AccountNotice::class);
        $this->assertDatabaseHas('audit_log', ['action' => 'job.ask_phone_choice']);
    }
}

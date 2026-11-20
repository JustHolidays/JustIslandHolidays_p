<?php

namespace Tests\Feature\Public;

use App\Jobs\SendEnquiryEmail;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\User;
use App\Support\Spam\AltchaGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        // ALTCHA is tested on its own (AltchaGuardTest); here a payload of 'solved' passes.
        $this->app->instance(AltchaGuard::class, new class extends AltchaGuard
        {
            public function verify(mixed $payload): bool
            {
                return $payload === 'solved';
            }
        });

        $this->listing = Listing::factory()->live()->titled('Villa Sunset')->withPrivate('inbox@owner.example')->create()->load(['island', 'area']);
    }

    private function send(array $overrides = [], ?Listing $listing = null): \Illuminate\Testing\TestResponse
    {
        $listing ??= $this->listing;

        return $this->from($listing->publicUrl('en'))->post('/en/enquiries', $overrides + [
            'listing_ref' => $listing->public_ref,
            'guest_name' => 'Sam Guest',
            'guest_email' => 'sam@guest.example',
            'check_in' => now()->addMonth()->toDateString(),
            'check_out' => now()->addMonth()->addWeek()->toDateString(),
            'adults' => 2,
            'message' => 'Is the villa free in June?',
            'consent' => '1',
            'altcha' => 'solved',
        ]);
    }

    public function test_enquiry_is_stored_and_emailed_to_the_listing_contact_with_reply_to_the_guest(): void
    {
        Mail::fake();

        $this->send()->assertRedirect($this->listing->publicUrl('en').'#enquiry')->assertSessionHas('enquiry_sent');

        $enquiry = Enquiry::sole();
        $this->assertSame($this->listing->owner_id, $enquiry->owner_id);
        $this->assertSame('en', $enquiry->lang);
        $this->assertNotNull($enquiry->consent_given_at);
        $this->assertNotNull($enquiry->email_sent_at);

        Mail::assertSent(EnquiryReceived::class, function (EnquiryReceived $mail) {
            return $mail->hasTo('inbox@owner.example')
                && $mail->hasReplyTo('sam@guest.example', 'Sam Guest');
        });
    }

    public function test_every_enquiry_has_a_reference_in_the_email_and_for_the_visitor(): void
    {
        Mail::fake();

        $this->send()->assertSessionHas('enquiry_ref', fn ($ref) => $ref === Enquiry::sole()->reference());
        $enquiry = Enquiry::sole();
        $ref = $enquiry->reference();
        $this->assertMatchesRegularExpression('/^E-\d{6}$/', $ref);
        $this->assertSame('E-'.str_pad((string) $enquiry->id, 6, '0', STR_PAD_LEFT), $ref);

        Mail::assertSent(EnquiryReceived::class, function (EnquiryReceived $mail) use ($ref) {
            $mail->assertSeeInHtml('Enquiry ref:')->assertSeeInHtml($ref)->assertSeeInText('Enquiry ref: '.$ref);

            return $mail->hasSubject('New enquiry '.$ref.': Villa Sunset');
        });

        $this->withSession(['enquiry_sent' => true, 'enquiry_ref' => $ref])->get($this->listing->publicUrl('en'))
            ->assertSee('Your reference is '.$ref.'.');

        // A second enquiry gets a different reference.
        $this->send(['guest_email' => 'other@guest.example']);
        $this->assertNotSame($ref, Enquiry::latest('id')->first()->reference());
    }

    public function test_typed_references_are_understood(): void
    {
        $this->assertSame(42, Enquiry::idFromReference('E-000042'));
        $this->assertSame(42, Enquiry::idFromReference(' e42 '));
        $this->assertSame(42, Enquiry::idFromReference('E 42'));
        $this->assertNull(Enquiry::idFromReference('Emma'));
        $this->assertNull(Enquiry::idFromReference('42'));
    }

    public function test_email_is_sent_from_the_queue(): void
    {
        Queue::fake();

        $this->send();

        Queue::assertPushed(SendEnquiryEmail::class, fn ($job) => $job->enquiryId === Enquiry::sole()->id);
    }

    public function test_raw_ip_is_never_stored(): void
    {
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->send();

        $enquiry = Enquiry::sole();
        $this->assertSame(64, strlen($enquiry->ip_hash));
        $this->assertStringNotContainsString('203.0.113.7', json_encode($enquiry->getAttributes()));
    }

    public function test_honeypot_pretends_success_but_stores_nothing(): void
    {
        Mail::fake();

        $this->send(['website' => 'http://spam.example'])->assertSessionHas('enquiry_sent');

        $this->assertSame(0, Enquiry::count());
        Mail::assertNothingSent();
    }

    public function test_altcha_is_required(): void
    {
        $this->send(['altcha' => ''])->assertSessionHasErrors('altcha');
        $this->send(['altcha' => 'forged'])->assertSessionHasErrors('altcha');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_consent_and_required_fields(): void
    {
        $this->send(['consent' => null])->assertSessionHasErrors('consent');
        $this->send(['guest_email' => 'not-an-email'])->assertSessionHasErrors('guest_email');
        $this->send(['message' => ''])->assertSessionHasErrors('message');
        $this->send(['message' => str_repeat('a', 4001)])->assertSessionHasErrors('message');
        $this->send(['check_out' => now()->addMonth()->subDay()->toDateString()])->assertSessionHasErrors('check_out');
        $this->send(['adults' => 0])->assertSessionHasErrors('adults');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_rate_limited_to_five_an_hour_per_visitor(): void
    {
        Mail::fake();

        foreach (range(1, 5) as $i) {
            $this->send()->assertSessionHasNoErrors();
        }
        $this->send()->assertSessionHasErrors('message');

        $this->assertSame(5, Enquiry::count());
    }

    public function test_affiliate_and_non_live_listings_refuse_enquiries(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $partner = Listing::factory()->live()->create(['owner_id' => $affiliate->id])->load(['island', 'area']);
        $draft = Listing::factory()->create()->load(['island', 'area']);

        $this->send(['listing_ref' => $partner->public_ref])->assertNotFound();
        $this->send(['listing_ref' => $draft->public_ref])->assertNotFound();
        $this->assertSame(0, Enquiry::count());
    }
}

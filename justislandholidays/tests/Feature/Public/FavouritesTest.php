<?php

namespace Tests\Feature\Public;

use App\Enums\Role;
use App\Listings\Favourites;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Visitor accounts and favourites: the heart on property pages and cards (20 Nov 2026). */
class FavouritesTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        Favourites::flush();
        $this->listing = Listing::factory()->live()->titled('Villa Sunset')->create()->load(['island', 'area']);
    }

    private function saved(User $user): array
    {
        return DB::table('favourites')->where('user_id', $user->id)->pluck('listing_id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_signed_out_visitors_see_an_outline_heart_that_goes_to_sign_in(): void
    {
        $this->get($this->listing->publicUrl('en'))->assertOk()
            ->assertSee('data-favourite="not-saved"', false)
            ->assertSee(route('favourites.sign-in', $this->listing->public_ref), false)
            ->assertSee('Save to favourites')
            ->assertSee(route('login'), false);
    }

    public function test_pressing_the_heart_saves_and_turns_it_red_and_pressing_again_removes_it(): void
    {
        $visitor = User::factory()->visitor()->create();
        $page = $this->listing->publicUrl('en');

        $this->actingAs($visitor)->from($page)->post(route('favourites.toggle', $this->listing->public_ref))->assertRedirect($page);
        $this->assertSame([$this->listing->id], $this->saved($visitor));
        Favourites::flush();

        $this->actingAs($visitor)->get($page)->assertOk()
            ->assertSee('Saved to your favourites')
            ->assertSee('class="mx-auto max-w-6xl px-4 py-6" data-auto-dismiss="5000"', false)   // spaced, and fades after 5 seconds
            ->assertSee('data-favourite="saved"', false)->assertSee('aria-pressed="true"', false)
            ->assertSee('text-red-600', false)->assertSee('Saved to favourites');

        $this->actingAs($visitor)->from($page)->post(route('favourites.toggle', $this->listing->public_ref));
        $this->assertSame([], $this->saved($visitor));
    }

    public function test_cards_have_the_heart_too(): void
    {
        $visitor = User::factory()->visitor()->create();
        DB::table('favourites')->insert(['user_id' => $visitor->id, 'listing_id' => $this->listing->id, 'created_at' => now()]);

        $this->actingAs($visitor)->get(route('islands.show', ['en', $this->listing->island->slug]))->assertOk()
            ->assertSee('data-favourite="saved"', false)
            ->assertSee('Remove Villa Sunset from your favourites');
    }

    public function test_signing_in_after_pressing_the_heart_saves_it_and_returns_to_the_home(): void
    {
        $visitor = User::factory()->visitor()->create(['password' => bcrypt('a-long-test-password')]);

        $this->get(route('favourites.sign-in', $this->listing->public_ref))->assertRedirect(route('login'));
        $this->get(route('login'))->assertSee('Sign in to save this home to your favourites');

        $this->post(route('login'), ['email' => $visitor->email, 'password' => 'a-long-test-password'])
            ->assertRedirect($this->listing->publicUrl());
        $this->assertSame([$this->listing->id], $this->saved($visitor));
    }

    public function test_a_new_visitor_account_saves_the_home_as_they_join(): void
    {
        $this->get(route('favourites.sign-in', $this->listing->public_ref));
        $this->get(route('register'))->assertOk()->assertSee('id="account_type_visitor"', false)
            ->assertSee('value="visitor" class="h-5 w-5" checked', false);

        $this->post(route('register'), [
            'name' => 'Vera Visitor', 'email' => 'vera@visitor.example', 'account_type' => 'visitor',
            'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password',
        ])->assertRedirect();

        $vera = User::where('email', 'vera@visitor.example')->sole();
        $this->assertSame(Role::Visitor, $vera->role);
        $this->assertSame([$this->listing->id], $this->saved($vera));
    }

    public function test_my_favourites_lists_saved_homes_and_can_remove_them(): void
    {
        $visitor = User::factory()->visitor()->create();
        $gone = Listing::factory()->live()->titled('Old Villa')->create();
        foreach ([$this->listing, $gone] as $l) {
            DB::table('favourites')->insert(['user_id' => $visitor->id, 'listing_id' => $l->id, 'created_at' => now()]);
        }
        $gone->forceFill(['status' => 'paused'])->save();

        $this->actingAs($visitor)->get(route('account.home'))->assertSee('My favourites')->assertSee(route('account.favourites'), false);
        $this->actingAs($visitor)->get(route('account.favourites'))->assertOk()
            ->assertSee('Villa Sunset')->assertSee($this->listing->publicUrl(), false)
            ->assertSee('Old Villa')->assertSee('No longer listed');

        $this->actingAs($visitor)->delete(route('account.favourites.destroy', $gone->id))->assertRedirect();
        $this->assertSame([$this->listing->id], $this->saved($visitor));

        // Another person's list is their own.
        $other = User::factory()->visitor()->create();
        $this->actingAs($other)->get(route('account.favourites'))->assertDontSee('Villa Sunset');
        $this->actingAs($other)->delete(route('account.favourites.destroy', $this->listing->id));
        $this->assertSame([$this->listing->id], $this->saved($visitor));
    }

    public function test_visitors_have_no_listings_billing_or_admin(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)->get(route('account.listings.index'))->assertForbidden();
        $this->actingAs($visitor)->get(route('account.billing'))->assertForbidden();
        $this->actingAs($visitor)->get(route('account.bookings'))->assertForbidden();
        $this->actingAs($visitor)->get('/admin')->assertForbidden();
        $this->actingAs($visitor)->get(route('account.home'))->assertOk()->assertDontSee(route('account.listings.index'), false);
    }

    public function test_only_live_homes_can_be_saved_and_deleting_a_home_removes_it_from_lists(): void
    {
        $visitor = User::factory()->visitor()->create();
        $draft = Listing::factory()->create();

        $this->actingAs($visitor)->post(route('favourites.toggle', $draft->public_ref))->assertNotFound();

        $this->actingAs($visitor)->post(route('favourites.toggle', $this->listing->public_ref));
        $this->listing->forceDelete();
        $this->assertSame([], $this->saved($visitor));
    }
}

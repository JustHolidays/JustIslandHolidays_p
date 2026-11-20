<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sign out from the account pages (20 Nov 2026). */
class SignOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_out_button_signs_out(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('account.home'))->assertOk()
            ->assertSee('action="'.route('logout').'"', false)->assertSee('Sign out');
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_sign_out_still_works_when_the_page_has_expired(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        // A form token from a page left open too long: the real check runs (tests normally skip it).
        $this->app->instance('env', 'production');
        $response = $this->post(route('logout'), ['_token' => 'out-of-date-token']);
        $this->app->instance('env', 'testing');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_other_forms_still_refuse_an_out_of_date_token(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        $this->app->instance('env', 'production');
        $response = $this->post(route('account.close.store'), ['_token' => 'out-of-date-token']);
        $this->app->instance('env', 'testing');

        $response->assertStatus(419);
        $this->assertAuthenticated();
    }
}

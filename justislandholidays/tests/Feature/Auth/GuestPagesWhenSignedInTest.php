<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Signed-in people opening a guest-only page go to their account, not an error (21 Nov 2026). */
class GuestPagesWhenSignedInTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_and_sign_up_pages_send_signed_in_people_to_their_account(): void
    {
        $user = User::factory()->owner()->create();

        foreach (['/account/login', '/account/register', '/account/forgot-password'] as $path) {
            $this->actingAs($user)->get($path)->assertRedirect('/account');
        }
    }
}

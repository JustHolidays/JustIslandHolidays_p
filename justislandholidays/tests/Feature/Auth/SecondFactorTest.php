<?php

namespace Tests\Feature\Auth;

use App\Auth\SecondFactor;
use App\Mail\SignInCode;
use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The admin's second sign-in step: an emailed one-time 6-digit code, or an authenticator app. */
class SecondFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function emailAdmin(): User
    {
        return User::factory()->superAdmin()->withoutTwoFactor()->create(['email' => 'boss@example.com', 'password' => 'a-long-password-1']);
    }

    /** Signs in with the password and returns the code that was emailed. */
    private function signInAndGetCode(User $user): string
    {
        $this->post('/account/login', ['email' => $user->email, 'password' => 'a-long-password-1'])->assertRedirect();
        $this->get('/admin')->assertRedirect(route('second-factor.show'));
        [$name, $domain] = explode('@', $user->email);
        $this->get(route('second-factor.show'))->assertOk()->assertSee(mb_substr($name, 0, 2)."\u{2022}")->assertSee('@'.$domain)->assertDontSee($user->email);

        $code = null;
        Mail::assertSent(SignInCode::class, function (SignInCode $mail) use ($user, &$code) {
            $code = $mail->code;

            return $mail->hasTo($user->email);
        });

        return $code;
    }

    public function test_admin_signs_in_with_password_then_the_emailed_code(): void
    {
        $admin = $this->emailAdmin();
        $code = $this->signInAndGetCode($admin);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertAuthenticatedAs($admin);

        $this->post(route('second-factor.verify'), ['code' => $code])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_the_code_works_only_once(): void
    {
        $admin = $this->emailAdmin();
        $code = $this->signInAndGetCode($admin);
        $this->post(route('second-factor.verify'), ['code' => $code])->assertRedirect();

        // Sign out and back in: the old code no longer works.
        $this->post('/account/logout');
        $this->post('/account/login', ['email' => $admin->email, 'password' => 'a-long-password-1']);
        Mail::fake();   // forget the first email

        $this->post(route('second-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect(route('second-factor.show'));
    }

    public function test_every_signed_in_page_waits_for_the_code(): void
    {
        $admin = $this->emailAdmin();
        $this->signInWithPasswordOnly($admin);

        foreach (['/admin', '/account', '/account/security', '/admin/appearance'] as $url) {
            $this->get($url)->assertRedirect(route('second-factor.show'));
        }
        // Signing out is still allowed.
        $this->post('/account/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_setting_up_an_app_also_waits_for_the_code(): void
    {
        $this->signInWithPasswordOnly($this->emailAdmin());

        $this->post('/account/user/two-factor-authentication')->assertRedirect(route('second-factor.show'));
    }

    public function test_wrong_codes_fail_and_five_void_the_code(): void
    {
        $admin = $this->emailAdmin();
        $code = $this->signInAndGetCode($admin);
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, SecondFactor::MAX_ATTEMPTS) as $i) {
            $this->post(route('second-factor.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Even the right code is refused now; a new one must be sent.
        $this->post(route('second-factor.verify'), ['code' => $code])->assertSessionHasErrors(['code' => 'That code has expired or has been used. Send a new one.']);
        $this->get('/admin')->assertRedirect(route('second-factor.show'));
        $this->assertDatabaseHas('audit_log', ['action' => 'auth.sign_in_code_voided']);
    }

    public function test_the_code_expires_after_ten_minutes(): void
    {
        $code = $this->signInAndGetCode($this->emailAdmin());

        $this->travel(SecondFactor::CODE_MINUTES + 1)->minutes();

        $this->post(route('second-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_a_new_code_replaces_the_old_one(): void
    {
        $admin = $this->emailAdmin();
        $first = $this->signInAndGetCode($admin);

        Mail::fake();
        $this->post(route('second-factor.resend'))->assertSessionHas('status');
        $second = null;
        Mail::assertSent(SignInCode::class, function ($m) use (&$second) {
            $second = $m->code;

            return true;
        });

        if ($first !== $second) {
            $this->post(route('second-factor.verify'), ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post(route('second-factor.verify'), ['code' => $second])->assertSessionHasNoErrors();
    }

    public function test_sending_is_limited(): void
    {
        $admin = $this->emailAdmin();
        $this->signInAndGetCode($admin);   // the first send

        foreach (range(2, SecondFactor::SENDS_PER_HOUR) as $i) {
            $this->post(route('second-factor.resend'))->assertSessionHas('status');
        }
        $this->post(route('second-factor.resend'))->assertSessionHasErrors('code');
        Mail::assertSentCount(SecondFactor::SENDS_PER_HOUR);
    }

    public function test_reloading_the_page_does_not_send_another_code(): void
    {
        $this->signInAndGetCode($this->emailAdmin());
        $this->get(route('second-factor.show'))->assertOk();

        Mail::assertSentCount(1);
    }

    public function test_the_code_is_stored_only_as_a_hash(): void
    {
        $admin = $this->emailAdmin();
        $code = $this->signInAndGetCode($admin);

        $stored = json_encode(cache()->get('sign-in-code:'.$admin->id));
        $this->assertStringNotContainsString($code, $stored);
    }

    public function test_island_admins_get_the_code_too(): void
    {
        $admin = User::factory()->islandAdmin(Island::factory()->create())->withoutTwoFactor()->create(['password' => 'a-long-password-1']);

        $code = $this->signInAndGetCode($admin);
        $this->post(route('second-factor.verify'), ['code' => $code]);

        $this->get('/admin')->assertOk();
    }

    public function test_owners_agents_and_affiliates_get_no_code(): void
    {
        foreach (['owner', 'agent', 'affiliate'] as $state) {
            $user = User::factory()->{$state}()->create(['password' => 'a-long-password-1']);
            $this->post('/account/login', ['email' => $user->email, 'password' => 'a-long-password-1']);
            $this->get('/account')->assertOk();
            $this->post('/account/logout');
        }

        Mail::assertNotSent(SignInCode::class);
    }

    // --- Admins who chose an authenticator app ---------------------------------------------

    public function test_app_admin_gets_no_email_and_uses_the_app(): void
    {
        $admin = User::factory()->superAdmin()->create(['password' => 'a-long-password-1']);

        // Remember-me style session: signed in, second step not done yet.
        $this->signInWithPasswordOnly($admin);
        $this->get(route('second-factor.show'))->assertOk()->assertSee('authenticator app');
        Mail::assertNotSent(SignInCode::class);

        $this->post(route('second-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');

        $current = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp(decrypt($admin->two_factor_secret));

        $this->post(route('second-factor.verify'), ['code' => $current])->assertSessionHasNoErrors();
        $this->get('/admin')->assertOk();
    }

    public function test_app_admin_can_use_a_recovery_code_once(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $recovery = $admin->recoveryCodes()[0];

        $this->signInWithPasswordOnly($admin);
        $this->post(route('second-factor.verify'), ['recovery_code' => $recovery])->assertSessionHasNoErrors();

        $this->assertNotContains($recovery, $admin->fresh()->recoveryCodes());
    }

    public function test_fortify_app_challenge_at_sign_in_counts_as_the_second_step(): void
    {
        $admin = User::factory()->superAdmin()->create(['password' => 'a-long-password-1']);
        $this->post('/account/login', ['email' => $admin->email, 'password' => 'a-long-password-1'])->assertRedirect('/account/two-factor-challenge');

        $current = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp(decrypt($admin->two_factor_secret));
        $this->post('/account/two-factor-challenge', ['code' => $current])->assertRedirect();

        $this->get('/admin')->assertOk();
        Mail::assertNotSent(SignInCode::class);
    }
}

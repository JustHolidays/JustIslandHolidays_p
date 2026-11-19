<?php

namespace App\Http\Controllers;

use App\Auth\SecondFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * /account/verify-sign-in: the admin's second step. An emailed 6-digit code by default,
 * or the authenticator app (or a recovery code) when the admin has set one up.
 */
class SecondFactorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! SecondFactor::required($user) || SecondFactor::passed($user)) {
            return redirect()->intended(route('account.home'));
        }

        $usesApp = SecondFactor::usesApp($user);
        $sendFailed = false;
        if (! $usesApp && ! SecondFactor::hasLiveCode($user)) {
            $sendFailed = ! SecondFactor::sendCode($user);
        }

        return view('auth.verify-sign-in', [
            'usesApp' => $usesApp,
            'email' => $this->maskedEmail($user->email),
            'minutes' => SecondFactor::CODE_MINUTES,
            'sendFailed' => $sendFailed,
        ]);
    }

    public function verify(Request $request, TwoFactorAuthenticationProvider $totp): RedirectResponse
    {
        $user = $request->user();
        if (! SecondFactor::required($user) || SecondFactor::passed($user)) {
            return redirect()->intended(route('account.home'));
        }

        $limiter = 'sign-in-code-check:'.$user->id;
        if (RateLimiter::tooManyAttempts($limiter, 10)) {
            throw ValidationException::withMessages(['code' => 'Too many tries. Wait '.RateLimiter::availableIn($limiter).' seconds and try again.']);
        }
        RateLimiter::hit($limiter, 300);

        $request->validate(['code' => ['nullable', 'string', 'max:50'], 'recovery_code' => ['nullable', 'string', 'max:50']]);
        $code = trim((string) $request->input('code'));
        $recovery = trim((string) $request->input('recovery_code'));

        $ok = false;
        if (SecondFactor::usesApp($user)) {
            if ($recovery !== '' && in_array($recovery, $user->recoveryCodes(), true)) {
                $user->replaceRecoveryCode($recovery);
                $ok = true;
            } elseif ($code !== '') {
                $ok = $totp->verify(decrypt($user->two_factor_secret), $code);
            }
        } else {
            $ok = $code !== '' && SecondFactor::checkCode($user, $code);
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'code' => SecondFactor::usesApp($user) || SecondFactor::hasLiveCode($user)
                    ? 'That code is not right. Check it and try again.'
                    : 'That code has expired or has been used. Send a new one.',
            ]);
        }

        RateLimiter::clear($limiter);
        $request->session()->regenerate();
        SecondFactor::markPassed($user);

        return redirect()->intended(route('account.home'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! SecondFactor::required($user) || SecondFactor::passed($user) || SecondFactor::usesApp($user)) {
            return redirect()->route('second-factor.show');
        }

        return SecondFactor::sendCode($user)
            ? back()->with('status', 'We have sent a new code. Only the newest code works.')
            : back()->withErrors(['code' => 'Too many codes have been sent in the last hour. Wait a while and try again.']);
    }

    private function maskedEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 2).str_repeat('•', max(1, mb_strlen($name) - 2)).'@'.$domain;
    }
}

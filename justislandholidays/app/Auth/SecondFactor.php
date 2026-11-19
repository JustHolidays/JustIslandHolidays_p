<?php

namespace App\Auth;

use App\Mail\SignInCode;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The second step of an admin's sign-in. Admins either use an authenticator app
 * (Fortify two-factor, when they have set one up) or, by default, a 6-digit code
 * emailed to them. Until this session has passed the second step, the
 * RequireSecondFactor middleware keeps the admin on the verify page.
 *
 * Email codes: random, stored only as a keyed hash in the cache, valid for
 * CODE_MINUTES, used once, and thrown away after MAX_ATTEMPTS wrong tries.
 */
final class SecondFactor
{
    public const SESSION_KEY = 'auth.second_factor';

    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** Sends allowed per user per hour (a resend replaces the earlier code). */
    public const SENDS_PER_HOUR = 5;

    public static function required(?User $user): bool
    {
        return $user !== null && $user->isAdmin();
    }

    public static function passed(?User $user): bool
    {
        return $user !== null && session(self::SESSION_KEY) === $user->id;
    }

    public static function markPassed(User $user): void
    {
        session()->put(self::SESSION_KEY, $user->id);
    }

    /** Uses the authenticator app when one is set up; otherwise an emailed code. */
    public static function usesApp(User $user): bool
    {
        return $user->hasConfirmedTwoFactor();
    }

    public static function hasLiveCode(User $user): bool
    {
        return Cache::has(self::cacheKey($user));
    }

    /** Emails a new code, replacing any earlier one. False when too many have been sent this hour. */
    public static function sendCode(User $user): bool
    {
        $limiter = 'sign-in-code-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($limiter, self::SENDS_PER_HOUR)) {
            return false;
        }
        RateLimiter::hit($limiter, 3600);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Cache::put(self::cacheKey($user), ['hash' => self::hash($user, $code), 'attempts' => 0], now()->addMinutes(self::CODE_MINUTES));

        // Sent straight away, not queued: the admin is waiting for it.
        Mail::to($user->email)->send(new SignInCode($code, self::CODE_MINUTES));

        return true;
    }

    /** Checks an emailed code. A correct code works once; too many wrong ones void it. */
    public static function checkCode(User $user, string $code): bool
    {
        $key = self::cacheKey($user);
        $stored = Cache::get($key);
        if (! is_array($stored)) {
            return false;
        }

        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) === 6 && hash_equals($stored['hash'], self::hash($user, $code))) {
            Cache::forget($key);

            return true;
        }

        $stored['attempts']++;
        if ($stored['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            Audit::record($user, 'auth.sign_in_code_voided', $user, null, ['reason' => 'too many wrong codes']);
        } else {
            Cache::put($key, $stored, now()->addMinutes(self::CODE_MINUTES));
        }

        return false;
    }

    public static function forgetCode(User $user): void
    {
        Cache::forget(self::cacheKey($user));
    }

    private static function cacheKey(User $user): string
    {
        return 'sign-in-code:'.$user->id;
    }

    private static function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, (string) config('app.key'));
    }
}

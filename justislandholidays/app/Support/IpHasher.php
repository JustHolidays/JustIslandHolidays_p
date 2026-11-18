<?php

namespace App\Support;

/**
 * The raw IP address is never stored. It is hashed with a secret salt and the
 * date, so the same visitor gets the same hash for one day only.
 */
final class IpHasher
{
    public static function hash(?string $ip, ?string $day = null): string
    {
        $day ??= now()->format('Y-m-d');

        return hash_hmac('sha256', $day.'|'.($ip ?? 'unknown'), (string) config('site.ip_hash_salt'));
    }
}

<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Audit;
use App\Support\Fetch\FetchException;
use App\Support\Fetch\SafeFetcher;

/**
 * Checks that an affiliate's Check availability link still opens. An error response or
 * no answer marks it broken, which hides the banner. Used by Recheck now and the nightly
 * job. Owner and agent links are their own responsibility and are never checked.
 */
final class DeeplinkChecker
{
    public function __construct(private SafeFetcher $fetcher)
    {
    }

    /** @return array{status: string, detail: string, final_host: ?string} */
    public function check(Listing $listing, ?User $actor = null): array
    {
        if (! $listing->deeplink_url) {
            return ['status' => 'unchecked', 'detail' => 'No link', 'final_host' => null];
        }
        // Owners and agents look after their own links; we never check them.
        if (! $listing->isAffiliateListing()) {
            return ['status' => $listing->deeplink_status, 'detail' => 'Owners and agents look after their own links. We do not check them.', 'final_host' => null];
        }

        try {
            $result = $this->fetcher->get($listing->deeplink_url, 65_536);
            $status = $result->ok() ? 'ok' : 'broken';
            $detail = 'The site answered '.$result->status.'.';
            $finalHost = $result->host();
        } catch (FetchException $e) {
            $status = 'broken';
            $detail = $e->getMessage();
            $finalHost = null;
        }

        $before = $listing->deeplink_status;
        $listing->forceFill(['deeplink_status' => $status, 'deeplink_checked_at' => now()])->saveQuietly();

        Audit::record($actor, 'deeplink.check', $listing, null, [
            'before' => ['deeplink_status' => $before],
            'after' => ['deeplink_status' => $status],
            'detail' => $detail,
            'final_host' => $finalHost,
        ]);

        return ['status' => $status, 'detail' => $detail, 'final_host' => $finalHost];
    }

    /** Hosts the deeplinks are expected to point to. Empty = any https address. */
    public static function allowedHosts(): array
    {
        return SiteSetting::find('deeplinks.allowed_hosts')?->value['hosts'] ?? [];
    }

    public static function isAllowedHost(?string $url): bool
    {
        $allowed = self::allowedHosts();
        if ($allowed === [] || ! $url) {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($allowed as $a) {
            $a = strtolower(ltrim($a, '.'));
            if ($host === $a || str_ends_with($host, '.'.$a)) {
                return true;
            }
        }

        return false;
    }
}

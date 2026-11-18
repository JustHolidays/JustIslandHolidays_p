<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Rough device type and bot detection from request headers. Nothing is stored except the result. */
final class VisitorAgent
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|scrape|fetch|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|headless|phantom|python|curl|wget|httpclient|okhttp|java\/|go-http|monitor|lighthouse|pingdom|uptime/i';

    public static function device(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        return match (true) {
            preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|Android(?!.*Mobile)/i', $ua) === 1 => 'tablet',
            preg_match('/Mobile|iPhone|iPod|Android.*Mobile|Windows Phone/i', $ua) === 1 => 'mobile',
            preg_match('/Windows NT|Macintosh|X11|Linux|CrOS/i', $ua) === 1 => 'desktop',
            default => 'unknown',
        };
    }

    /** Known bots, empty user agents and link prefetchers. */
    public static function isBot(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        if ($ua === '' || preg_match(self::BOT_PATTERN, $ua) === 1) {
            return true;
        }

        $purpose = strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '').' '.$request->header('X-Moz', ''));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }
}

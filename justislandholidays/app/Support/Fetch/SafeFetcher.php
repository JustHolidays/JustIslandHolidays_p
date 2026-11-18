<?php

namespace App\Support\Fetch;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a page from another website safely (deeplink checks, URL import):
 * https only, no private or internal addresses (checked on every redirect, and the
 * connection is pinned to the checked address so DNS cannot be switched mid-request),
 * short timeouts, a size cap and a limited number of redirects.
 */
class SafeFetcher
{
    private Closure $resolver;

    public function __construct(
        ?Closure $resolver = null,
        private int $timeoutSeconds = 8,
        private int $maxRedirects = 3,
    ) {
        $this->resolver = $resolver ?? fn (string $host): array => self::dns($host);
    }

    public function get(string $url, int $maxBytes = 2_000_000, bool $allowTruncate = true): FetchResult
    {
        $redirects = [];

        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            [$host, $ip] = $this->check($url);

            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$ip}"]],
                ])
                    ->timeout($this->timeoutSeconds)
                    ->connectTimeout(4)
                    ->withHeaders([
                        'User-Agent' => 'JustIslandHolidaysBot/1.0 (link check)',
                        'Accept' => 'text/html,application/xhtml+xml,image/*;q=0.9,*/*;q=0.5',
                    ])
                    ->get($url);
            } catch (ConnectionException $e) {
                throw new FetchException('The site did not answer in time.');
            } catch (Throwable $e) {
                throw new FetchException('The page could not be fetched.');
            }

            $status = $response->status();

            if ($status >= 300 && $status < 400 && $response->header('Location')) {
                $next = $this->absolute($url, $response->header('Location'));
                $redirects[] = $next;
                $url = $next;

                continue;
            }

            [$body, $truncated] = $this->read($response, $maxBytes);
            if ($truncated && ! $allowTruncate) {
                throw new FetchException('The file is too large.');
            }

            return new FetchResult($url, $status, $body, $response->header('Content-Type') ?: null, $truncated, $redirects);
        }

        throw new FetchException('The page redirects too many times.');
    }

    /** @return array{0: string, 1: string} [host, public IP to connect to] */
    private function check(string $url): array
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new FetchException('Only https addresses can be fetched.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new FetchException('Addresses with a username or password are not allowed.');
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            throw new FetchException('Only the standard https port can be used.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new FetchException('That address is not on the public internet.');
        }

        $ips = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) ? [trim($host, '[]')] : ($this->resolver)($host);
        if ($ips === []) {
            throw new FetchException('That website address could not be found.');
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new FetchException('That address is not on the public internet.');
            }
        }

        return [$host, $ips[0]];
    }

    public static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! str_starts_with($ip, '100.64.')      // carrier-grade NAT
            && ! str_starts_with(strtolower($ip), 'fe80:')
            && ! in_array($ip, ['0.0.0.0', '::', '::1'], true);
    }

    /** @return list<string> */
    private static function dns(string $host): array
    {
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    private function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'];

        if (str_starts_with($location, '//')) {
            return $b['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $dir = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';

        return $origin.$dir.$location;
    }

    /** @return array{0: string, 1: bool} */
    private function read($response, int $maxBytes): array
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(65536);
            if (strlen($body) > $maxBytes) {
                return [substr($body, 0, $maxBytes), true];
            }
        }

        return [$body, false];
    }
}

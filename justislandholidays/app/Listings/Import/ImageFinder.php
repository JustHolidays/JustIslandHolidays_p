<?php

namespace App\Listings\Import;

use App\Support\Fetch\FetchException;
use App\Support\Fetch\SafeFetcher;

/**
 * Picks a photo big enough to use from the ones a property page offers (21 Nov 2026).
 *
 * Many pages share only a small preview (for example 600 pixels wide) although the provider
 * serves bigger copies. For each candidate, main photo first, it tries larger versions where
 * the address follows a clear size pattern, then the candidate itself, and stops at the first
 * photo at least MIN_WIDTH wide. Everything is fetched through SafeFetcher, with a cap on how
 * many fetches one import makes. Nothing is stored here: the import copies the chosen photo
 * only on save, after the permission box is ticked.
 */
final class ImageFinder
{
    public const MIN_WIDTH = 800;

    public const MAX_FETCHES = 8;

    public const MAX_BYTES = 10_000_000;

    public function __construct(private SafeFetcher $fetcher)
    {
    }

    /**
     * @param  list<string>  $candidates
     * @return array{url: ?string, width: ?int, height: ?int, largest: ?array{width: int, height: int}}
     */
    public function find(array $candidates): array
    {
        $result = ['url' => null, 'width' => null, 'height' => null, 'largest' => null];
        $tried = [];
        $fetches = 0;

        foreach ($candidates as $candidate) {
            foreach ([...self::largerVersions($candidate), $candidate] as $url) {
                if (isset($tried[$url])) {
                    continue;
                }
                if ($fetches >= self::MAX_FETCHES) {
                    return $result;
                }
                $tried[$url] = true;
                $fetches++;

                $size = $this->measure($url);
                if (! $size) {
                    continue;
                }
                if ($size[0] >= self::MIN_WIDTH) {
                    return ['url' => $url, 'width' => $size[0], 'height' => $size[1], 'largest' => null];
                }
                if (($result['largest']['width'] ?? 0) < $size[0]) {
                    $result['largest'] = ['width' => $size[0], 'height' => $size[1]];
                }
            }
        }

        return $result;
    }

    /**
     * Larger versions of a photo address, biggest likely first, for common size patterns:
     * a size folder (/pic/600/ to /pic/1600/ and /pic/1024/), a width in the query
     * (?w=600), a WordPress size suffix (-600x400.jpg), and a Drupal image style
     * (/styles/name/public/x.jpg.webp to the original /x.jpg).
     *
     * @return list<string>
     */
    public static function largerVersions(string $url): array
    {
        $parts = parse_url($url);
        if (! isset($parts['host'], $parts['path'])) {
            return [];
        }
        $base = 'https://'.$parts['host'];
        $path = $parts['path'];
        $query = $parts['query'] ?? '';
        $with = fn (string $p, string $q = '') => $base.$p.($q !== '' ? '?'.$q : '');
        $out = [];

        // Drupal image style: /sites/x/files/styles/<style>/public/name.jpg(.webp) -> /sites/x/files/name.jpg
        if (preg_match('#^(.*/)styles/[^/]+/public/(.+?)(\.webp)?$#i', $path, $m) && preg_match('/\.(jpe?g|png|webp)$/i', $m[2])) {
            $out[] = $with($m[1].$m[2]);
        }

        // WordPress size suffix: name-600x400.jpg -> name.jpg
        if (preg_match('/^(.*)-\d{2,4}x\d{2,4}(\.(jpe?g|png|webp))$/i', $path, $m)) {
            $out[] = $with($m[1].$m[2], $query);
        }

        // Width in the query: w=600 / width=600 (heights dropped so the shape is kept).
        if ($query !== '') {
            parse_str($query, $q);
            foreach (['w', 'width', 'maxwidth', 'max_width'] as $key) {
                if (isset($q[$key]) && is_numeric($q[$key]) && (int) $q[$key] < 1600) {
                    $q[$key] = 1600;
                    unset($q['h'], $q['height'], $q['maxheight'], $q['max_height']);
                    $out[] = $with($path, http_build_query($q));
                    break;
                }
            }
        }

        // A size folder: /pic/600/name.jpg -> /pic/1600/name.jpg, then /pic/1024/name.jpg
        $segments = explode('/', $path);
        foreach ($segments as $i => $segment) {
            if (ctype_digit($segment) && (int) $segment >= 100 && (int) $segment < 1600 && $i < count($segments) - 1) {
                foreach ([1600, 1024] as $bigger) {
                    if ($bigger > (int) $segment) {
                        $copy = $segments;
                        $copy[$i] = (string) $bigger;
                        $out[] = $with(implode('/', $copy), $query);
                    }
                }
                break;
            }
        }

        return array_values(array_unique(array_filter($out, fn ($u) => $u !== $url)));
    }

    /** @return array{0: int, 1: int}|null width and height of a JPG, PNG or WebP photo */
    private function measure(string $url): ?array
    {
        try {
            $response = $this->fetcher->get($url, self::MAX_BYTES, allowTruncate: false);
        } catch (FetchException) {
            return null;
        }
        if (! $response->ok()) {
            return null;
        }
        $info = @getimagesizefromstring($response->body);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }
}

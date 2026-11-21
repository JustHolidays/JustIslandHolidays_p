<?php

namespace App\Listings\Import;

use DOMDocument;
use DOMXPath;

/**
 * Reads what a property page offers: Open Graph tags, the page title and description,
 * and schema.org data (JSON-LD). Fills what it finds; everything else is left empty for
 * the admin to complete.
 */
final class PageParser
{
    /** @return array<string, mixed> */
    public function parse(string $html, string $pageUrl): array
    {
        $out = ['title' => null, 'summary' => null, 'image_url' => null, 'lat' => null, 'lng' => null,
            'bedrooms' => null, 'bathrooms' => null, 'sleeps' => null, 'locality' => null, 'found' => [], 'image_candidates' => []];

        if (trim($html) === '') {
            return $out;
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xp = new DOMXPath($doc);

        $meta = fn (string $q) => ($n = $xp->query($q)->item(0)) ? trim((string) $n->getAttribute('content')) ?: null : null;

        $out['title'] = $meta('//meta[@property="og:title"]') ?? (($t = $xp->query('//title')->item(0)) ? trim($t->textContent) : null);
        $out['summary'] = $meta('//meta[@property="og:description"]') ?? $meta('//meta[@name="description"]');
        $out['image_url'] = $meta('//meta[@property="og:image:secure_url"]') ?? $meta('//meta[@property="og:image"]');
        $out['lat'] = $meta('//meta[@property="place:location:latitude"]');
        $out['lng'] = $meta('//meta[@property="place:location:longitude"]');

        foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            foreach ($this->nodes($data) as $node) {
                $this->fromSchema($node, $out);
            }
        }

        foreach (['title', 'summary'] as $k) {
            if ($out[$k] !== null) {
                $out[$k] = mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($out[$k]), ENT_QUOTES | ENT_HTML5))), 0, $k === 'title' ? 160 : 500) ?: null;
            }
        }
        $out['image_url'] = $out['image_url'] ? $this->absolute($out['image_url'], $pageUrl) : null;
        foreach (['lat', 'lng'] as $k) {
            $out[$k] = is_numeric($out[$k]) ? round((float) $out[$k], 6) : null;
        }

        $out['found'] = array_keys(array_filter(array_diff_key($out, ['found' => 1, 'image_candidates' => 1]), fn ($v) => $v !== null));
        // Every photo the page offers, main one first (21 Nov 2026): ImageFinder picks one big enough.
        $out['image_candidates'] = $this->imageCandidates($xp, $out['image_url'], $pageUrl);

        return $out;
    }

    public const MAX_CANDIDATES = 20;

    /**
     * Photo addresses on the page, in order: the main photo (Open Graph or schema.org), other
     * sharing images, every schema.org image, then the page's own pictures (largest srcset
     * entry first). https only, no SVG or GIF, no duplicates.
     *
     * @return list<string>
     */
    private function imageCandidates(DOMXPath $xp, ?string $main, string $pageUrl): array
    {
        $raw = [$main];
        foreach (['//meta[@property="og:image:secure_url"]', '//meta[@property="og:image"]', '//meta[@name="twitter:image"]', '//meta[@property="twitter:image"]'] as $q) {
            foreach ($xp->query($q) as $n) {
                $raw[] = $n->getAttribute('content');
            }
        }
        foreach ($xp->query('//link[@rel="image_src"]') as $n) {
            $raw[] = $n->getAttribute('href');
        }
        foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
            array_push($raw, ...$this->schemaImages(json_decode(trim($script->textContent), true)));
        }
        foreach ($xp->query('//img | //source') as $n) {
            foreach (['srcset', 'data-srcset'] as $attr) {
                if ($best = $this->largestFromSrcset((string) $n->getAttribute($attr))) {
                    $raw[] = $best;
                }
            }
            foreach (['data-src', 'data-lazy-src', 'data-original', 'src'] as $attr) {
                $raw[] = $n->getAttribute($attr);
            }
        }

        $out = [];
        foreach ($raw as $url) {
            $url = is_string($url) ? trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) : '';
            if ($url === '' || str_starts_with($url, 'data:')) {
                continue;
            }
            $abs = $this->absolute($url, $pageUrl);
            if (! $abs || preg_match('/\.(svg|gif)(\?|$)/i', $abs) || in_array($abs, $out, true)) {
                continue;
            }
            $out[] = $abs;
            if (count($out) >= self::MAX_CANDIDATES) {
                break;
            }
        }

        return $out;
    }

    /** @return list<string> every image address in schema.org data */
    private function schemaImages(mixed $data): array
    {
        $found = [];
        foreach ($this->nodes($data) as $node) {
            foreach ([$node['image'] ?? null, $node['photo'] ?? null, $node['containsPlace']['image'] ?? null] as $image) {
                foreach (is_array($image) && array_is_list($image) ? $image : [$image] as $item) {
                    $found[] = is_array($item) ? ($item['url'] ?? $item['contentUrl'] ?? null) : $item;
                }
            }
        }

        return array_values(array_filter($found, 'is_string'));
    }

    /** The widest entry of a srcset ("a.jpg 600w, b.jpg 1200w" gives b.jpg). */
    private function largestFromSrcset(string $srcset): ?string
    {
        $best = null;
        $bestSize = -1.0;
        foreach (preg_split('/,\s+/', trim($srcset)) ?: [] as $part) {
            $bits = preg_split('/\s+/', trim($part));
            if (($bits[0] ?? '') === '') {
                continue;
            }
            $size = isset($bits[1]) ? (float) $bits[1] : 1.0;   // "1200w" or "2x"
            if ($size > $bestSize) {
                [$best, $bestSize] = [$bits[0], $size];
            }
        }

        return $best;
    }

    /** Flattens @graph and lists into individual schema.org nodes. */
    private function nodes(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['@graph'])) {
            return $this->nodes($data['@graph']);
        }
        if (array_is_list($data)) {
            return array_merge(...array_map(fn ($d) => $this->nodes($d), $data ?: [[]]));
        }

        return [$data];
    }

    private function fromSchema(array $n, array &$out): void
    {
        $types = (array) ($n['@type'] ?? []);
        $relevant = array_intersect($types, ['VacationRental', 'LodgingBusiness', 'Accommodation', 'House', 'Apartment', 'SingleFamilyResidence', 'Product', 'Place', 'Hotel', 'Residence']);
        if ($relevant === []) {
            return;
        }

        $out['title'] ??= is_string($n['name'] ?? null) ? $n['name'] : null;
        $out['summary'] ??= is_string($n['description'] ?? null) ? $n['description'] : null;

        $image = $n['image'] ?? null;
        $image = is_array($image) ? ($image['url'] ?? $image[0] ?? null) : $image;
        $out['image_url'] ??= is_array($image) ? ($image['url'] ?? null) : (is_string($image) ? $image : null);

        $geo = $n['geo'] ?? ($n['containsPlace']['geo'] ?? null);
        if (is_array($geo)) {
            $out['lat'] ??= $geo['latitude'] ?? null;
            $out['lng'] ??= $geo['longitude'] ?? null;
        }

        $place = $n['containsPlace'] ?? $n;
        $num = fn ($v) => is_numeric($v) ? (int) $v : (is_array($v) && is_numeric($v['value'] ?? null) ? (int) $v['value'] : null);
        $out['bedrooms'] ??= $num($place['numberOfBedrooms'] ?? $place['numberOfRooms'] ?? null);
        $out['bathrooms'] ??= $num($place['numberOfBathroomsTotal'] ?? null);
        $occupancy = $place['occupancy'] ?? null;
        $out['sleeps'] ??= $num(is_array($occupancy) ? ($occupancy['maxValue'] ?? $occupancy['value'] ?? null) : $occupancy);

        $address = $n['address'] ?? null;
        $out['locality'] ??= is_array($address) && is_string($address['addressLocality'] ?? null) ? $address['addressLocality'] : null;
    }

    private function absolute(string $url, string $base): ?string
    {
        if (str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (str_starts_with($url, '/')) {
            $b = parse_url($base);

            return 'https://'.$b['host'].$url;
        }

        return null;   // http images are not copied
    }
}

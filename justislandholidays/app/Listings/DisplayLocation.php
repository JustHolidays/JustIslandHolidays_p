<?php

namespace App\Listings;

/**
 * The exact location stays private. The public map pin is moved about 300 m in a
 * random direction, once, when the owner saves the location.
 */
final class DisplayLocation
{
    // A box around the Balearic Islands, to catch swapped or mistyped coordinates.
    public const MIN_LAT = 38.5;
    public const MAX_LAT = 40.2;
    public const MIN_LNG = 1.1;
    public const MAX_LNG = 4.5;

    /** @return array{0: float, 1: float} [lat, lng] */
    public static function offset(float $lat, float $lng, int $minMetres = 250, int $maxMetres = 350): array
    {
        $distance = random_int($minMetres, $maxMetres);
        $bearing = deg2rad(random_int(0, 359));

        $dLat = ($distance * cos($bearing)) / 111_320;
        $dLng = ($distance * sin($bearing)) / (111_320 * cos(deg2rad($lat)));

        return [round($lat + $dLat, 6), round($lng + $dLng, 6)];
    }

    /** Distance in metres between two points (haversine). */
    public static function metresBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6_371_000;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * Accepts "39.9112, 4.2650" as copied from a map app.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function parse(?string $input): ?array
    {
        if (! preg_match('/^\s*(-?\d{1,3}(?:\.\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', (string) $input, $m)) {
            return null;
        }

        $lat = (float) $m[1];
        $lng = (float) $m[2];

        if ($lat < self::MIN_LAT || $lat > self::MAX_LAT || $lng < self::MIN_LNG || $lng > self::MAX_LNG) {
            return null;
        }

        return [$lat, $lng];
    }
}

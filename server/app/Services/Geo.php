<?php

namespace App\Services;

/** Small geography helpers. Points are [lat, lng] arrays. No Google API needed. */
class Geo
{
    public const EARTH_KM = 6371.0088;

    /** Roads are longer than straight lines; this multiplier is a common city estimate. */
    public const ROAD_FACTOR = 1.3;

    public static function haversineKm(array $a, array $b): float
    {
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_KM * asin(min(1.0, sqrt($h)));
    }

    /** Estimated driving distance in km. */
    public static function roadKm(array $a, array $b): float
    {
        return self::haversineKm($a, $b) * self::ROAD_FACTOR;
    }

    /** Flat x/y position in km of $p relative to $origin (accurate enough inside one city). */
    public static function plane(array $p, array $origin): array
    {
        return [
            deg2rad($p[1] - $origin[1]) * self::EARTH_KM * cos(deg2rad($origin[0])),
            deg2rad($p[0] - $origin[0]) * self::EARTH_KM,
        ];
    }
}

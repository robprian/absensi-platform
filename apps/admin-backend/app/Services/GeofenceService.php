<?php

namespace App\Services;

/**
 * Pure geographic helpers. All distance math in meters.
 */
class GeofenceService
{
    public const EARTH_RADIUS_M = 6371000;

    public static function distanceMeters(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * self::EARTH_RADIUS_M * asin(sqrt($a));
    }

    public static function validCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180
            && !($lat == 0.0 && $lng == 0.0); // reject null-island placeholder
    }

    /**
     * @param array{latitude:float,longitude:float,radius_meters:int,geofence_enabled:bool} $site
     * @return array{inside: bool, distance_meters: float}
     */
    public static function checkSite(float $lat, float $lng, array $site): array
    {
        $distance = self::distanceMeters($lat, $lng, (float) $site['latitude'], (float) $site['longitude']);
        return [
            'inside' => !$site['geofence_enabled'] || $distance <= (int) $site['radius_meters'],
            'distance_meters' => round($distance, 1),
        ];
    }
}

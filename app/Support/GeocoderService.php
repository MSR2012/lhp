<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves a latitude/longitude pair to a human-readable city label and
 * IANA timezone by finding the nearest anchor in GeoAnchorMap.
 *
 * Uses the Haversine formula for great-circle distance. No external API
 * calls — resolution is entirely offline against the static anchor table.
 *
 * Accuracy guarantee: every seeded event coordinate is jittered ±0.5°
 * (~55 km) around one of the 65 known anchors, which are always hundreds
 * of km apart. The nearest anchor is therefore always unambiguous.
 */
final class GeocoderService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Resolve a coordinate to the nearest city label and IANA timezone.
     *
     * @return array{address: string, timezone: string}
     */
    public function resolve(float $lat, float $lng): array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach (GeoAnchorMap::ANCHORS as [$anchorLat, $anchorLng, $city, $timezone]) {
            $distance = $this->haversine($lat, $lng, (float) $anchorLat, (float) $anchorLng);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = ['address' => $city, 'timezone' => $timezone];
            }
        }

        /** @var array{address: string, timezone: string} $nearest */
        return $nearest;
    }

    /**
     * Calculate the great-circle distance in kilometres between two points
     * using the Haversine formula.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2.0) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2.0) ** 2;

        return self::EARTH_RADIUS_KM * 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
    }
}

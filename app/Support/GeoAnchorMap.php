<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Static lookup table mapping the 65 city anchors used by EventSeeder
 * to their human-readable labels and IANA timezone identifiers.
 *
 * Each entry mirrors EventSeeder::CITY_ANCHORS in the same index order so
 * that GeocoderService can resolve any seeded lat/lng by nearest-neighbour.
 *
 * Format: [latitude, longitude, city_label, iana_timezone]
 */
final class GeoAnchorMap
{
    /**
     * @var array<int, array{0: float, 1: float, 2: string, 3: string}>
     */
    public const ANCHORS = [
        // United States
        [40.7128,   -74.0060,  'New York, NY',           'America/New_York'],
        [34.0522,  -118.2437,  'Los Angeles, CA',        'America/Los_Angeles'],
        [41.8781,   -87.6298,  'Chicago, IL',            'America/Chicago'],
        [29.7604,   -95.3698,  'Houston, TX',            'America/Chicago'],
        [33.4484,  -112.0740,  'Phoenix, AZ',            'America/Phoenix'],
        [39.9526,   -75.1652,  'Philadelphia, PA',       'America/New_York'],
        [29.4241,   -98.4936,  'San Antonio, TX',        'America/Chicago'],
        [32.7157,  -117.1611,  'San Diego, CA',          'America/Los_Angeles'],
        [32.7767,   -96.7970,  'Dallas, TX',             'America/Chicago'],
        [37.3382,  -121.8863,  'San Jose, CA',           'America/Los_Angeles'],
        [30.2672,   -97.7431,  'Austin, TX',             'America/Chicago'],
        [37.7749,  -122.4194,  'San Francisco, CA',      'America/Los_Angeles'],
        [47.6062,  -122.3321,  'Seattle, WA',            'America/Los_Angeles'],
        [39.7392,  -104.9903,  'Denver, CO',             'America/Denver'],
        [42.3601,   -71.0589,  'Boston, MA',             'America/New_York'],
        [36.1699,  -115.1398,  'Las Vegas, NV',          'America/Los_Angeles'],
        [25.7617,   -80.1918,  'Miami, FL',              'America/New_York'],
        [33.7490,   -84.3880,  'Atlanta, GA',            'America/New_York'],
        [38.9072,   -77.0369,  'Washington, DC',         'America/New_York'],
        [36.1627,   -86.7816,  'Nashville, TN',          'America/Chicago'],
        [45.5152,  -122.6784,  'Portland, OR',           'America/Los_Angeles'],
        [29.9511,   -90.0715,  'New Orleans, LA',        'America/Chicago'],
        // Canada
        [43.6532,   -79.3832,  'Toronto, ON',            'America/Toronto'],
        [45.5019,   -73.5674,  'Montreal, QC',           'America/Toronto'],
        [49.2827,  -123.1207,  'Vancouver, BC',          'America/Vancouver'],
        [51.0447,  -114.0719,  'Calgary, AB',            'America/Edmonton'],
        [45.4215,   -75.6972,  'Ottawa, ON',             'America/Toronto'],
        [53.5461,  -113.4938,  'Edmonton, AB',           'America/Edmonton'],
        [46.8139,   -71.2080,  'Quebec City, QC',        'America/Toronto'],
        [49.8951,   -97.1384,  'Winnipeg, MB',           'America/Winnipeg'],
        // Mexico
        [19.4326,   -99.1332,  'Mexico City',            'America/Mexico_City'],
        [20.6597,  -103.3496,  'Guadalajara',            'America/Mexico_City'],
        [25.6866,  -100.3161,  'Monterrey',              'America/Monterrey'],
        [19.0414,   -98.2063,  'Puebla',                 'America/Mexico_City'],
        [32.5149,  -117.0382,  'Tijuana',                'America/Tijuana'],
        [21.1619,   -86.8515,  'Cancún',                 'America/Cancun'],
        [20.9674,   -89.5926,  'Mérida',                 'America/Mexico_City'],
        // Europe
        [51.5074,    -0.1278,  'London, UK',             'Europe/London'],
        [48.8566,     2.3522,  'Paris, France',          'Europe/Paris'],
        [52.5200,    13.4050,  'Berlin, Germany',        'Europe/Berlin'],
        [40.4168,    -3.7038,  'Madrid, Spain',          'Europe/Madrid'],
        [41.9028,    12.4964,  'Rome, Italy',            'Europe/Rome'],
        [52.3676,     4.9041,  'Amsterdam, Netherlands', 'Europe/Amsterdam'],
        [41.3851,     2.1734,  'Barcelona, Spain',       'Europe/Madrid'],
        [48.1351,    11.5820,  'Munich, Germany',        'Europe/Berlin'],
        [45.4642,     9.1900,  'Milan, Italy',           'Europe/Rome'],
        [48.2082,    16.3738,  'Vienna, Austria',        'Europe/Vienna'],
        [50.0755,    14.4378,  'Prague, Czechia',        'Europe/Prague'],
        [38.7223,    -9.1393,  'Lisbon, Portugal',       'Europe/Lisbon'],
        [53.3498,    -6.2603,  'Dublin, Ireland',        'Europe/Dublin'],
        [55.6761,    12.5683,  'Copenhagen, Denmark',    'Europe/Copenhagen'],
        [59.3293,    18.0686,  'Stockholm, Sweden',      'Europe/Stockholm'],
        [59.9139,    10.7522,  'Oslo, Norway',           'Europe/Oslo'],
        [60.1699,    24.9384,  'Helsinki, Finland',      'Europe/Helsinki'],
        [50.8503,     4.3517,  'Brussels, Belgium',      'Europe/Brussels'],
        [47.3769,     8.5417,  'Zurich, Switzerland',    'Europe/Zurich'],
        [52.2297,    21.0122,  'Warsaw, Poland',         'Europe/Warsaw'],
        [47.4979,    19.0402,  'Budapest, Hungary',      'Europe/Budapest'],
        [37.9838,    23.7275,  'Athens, Greece',         'Europe/Athens'],
        [45.7640,     4.8357,  'Lyon, France',           'Europe/Paris'],
        [53.5511,     9.9937,  'Hamburg, Germany',       'Europe/Berlin'],
        [53.4808,    -2.2426,  'Manchester, UK',         'Europe/London'],
        [55.9533,    -3.1883,  'Edinburgh, UK',          'Europe/London'],
        [50.1109,     8.6821,  'Frankfurt, Germany',     'Europe/Berlin'],
        [50.0647,    19.9450,  'Kraków, Poland',         'Europe/Warsaw'],
        [41.1579,    -8.6291,  'Porto, Portugal',        'Europe/Lisbon'],
        [40.8518,    14.2681,  'Naples, Italy',          'Europe/Rome'],
        // Global hubs
        [35.6762,   139.6503,  'Tokyo, Japan',           'Asia/Tokyo'],
        [37.5665,   126.9780,  'Seoul, South Korea',     'Asia/Seoul'],
        [ 1.3521,   103.8198,  'Singapore',              'Asia/Singapore'],
        [-33.8688,  151.2093,  'Sydney, Australia',      'Australia/Sydney'],
        [-37.8136,  144.9631,  'Melbourne, Australia',   'Australia/Melbourne'],
        [25.2048,    55.2708,  'Dubai, UAE',             'Asia/Dubai'],
        [-23.5505,  -46.6333,  'São Paulo, Brazil',      'America/Sao_Paulo'],
        [-34.6037,  -58.3816,  'Buenos Aires, Argentina','America/Argentina/Buenos_Aires'],
    ];

    /**
     * Find the anchor whose city label matches (case-insensitive, partial match).
     * Returns [lat, lng, city_label, iana_timezone] or null if not found.
     *
     * @return array{0: float, 1: float, 2: string, 3: string}|null
     */
    public static function findAnchorByCity(string $city): ?array
    {
        $needle = mb_strtolower($city);

        foreach (self::ANCHORS as $anchor) {
            if (str_contains(mb_strtolower($anchor[2]), $needle)) {
                return $anchor;
            }
        }

        return null;
    }
}

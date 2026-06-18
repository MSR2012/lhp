<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\GeocoderService;
use Tests\TestCase;

/**
 * @testdox GeocoderService
 */
class GeocoderServiceTest extends TestCase
{
    private GeocoderService $geocoder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geocoder = new GeocoderService();
    }

    /**
     * @testdox resolves a coordinate exactly on a known anchor to the correct city
     */
    public function test_resolves_exact_anchor_coordinate(): void
    {
        // New York anchor: [40.7128, -74.0060]
        $result = $this->geocoder->resolve(40.7128, -74.0060);

        $this->assertSame('New York, NY', $result['address']);
        $this->assertSame('America/New_York', $result['timezone']);
    }

    /**
     * @testdox resolves a coordinate jittered near an anchor to the same city
     */
    public function test_resolves_jittered_coordinate_to_nearest_anchor(): void
    {
        // Tokyo anchor [35.6762, 139.6503] jittered by ~0.3° — still closest to Tokyo
        $result = $this->geocoder->resolve(35.9500, 139.9000);

        $this->assertSame('Tokyo, Japan', $result['address']);
        $this->assertSame('Asia/Tokyo', $result['timezone']);
    }

    /**
     * @testdox resolves a European coordinate to the correct city and timezone
     */
    public function test_resolves_european_coordinate(): void
    {
        // Paris anchor: [48.8566, 2.3522]
        $result = $this->geocoder->resolve(48.8600, 2.3600);

        $this->assertSame('Paris, France', $result['address']);
        $this->assertSame('Europe/Paris', $result['timezone']);
    }

    /**
     * @testdox resolves a southern hemisphere coordinate correctly
     */
    public function test_resolves_southern_hemisphere_coordinate(): void
    {
        // Sydney anchor: [-33.8688, 151.2093]
        $result = $this->geocoder->resolve(-33.8500, 151.2000);

        $this->assertSame('Sydney, Australia', $result['address']);
        $this->assertSame('Australia/Sydney', $result['timezone']);
    }

    /**
     * @testdox always returns both address and timezone keys
     */
    public function test_result_always_contains_address_and_timezone_keys(): void
    {
        $result = $this->geocoder->resolve(51.5074, -0.1278);

        $this->assertArrayHasKey('address', $result);
        $this->assertArrayHasKey('timezone', $result);
        $this->assertNotEmpty($result['address']);
        $this->assertNotEmpty($result['timezone']);
    }

    /**
     * @testdox does not confuse cities across continents
     */
    public function test_does_not_confuse_cities_across_continents(): void
    {
        // São Paulo anchor: [-23.5505, -46.6333] — must not resolve to a US city
        $result = $this->geocoder->resolve(-23.5505, -46.6333);

        $this->assertSame('São Paulo, Brazil', $result['address']);
        $this->assertSame('America/Sao_Paulo', $result['timezone']);
    }
}

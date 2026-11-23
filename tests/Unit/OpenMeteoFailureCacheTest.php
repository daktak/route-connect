<?php

namespace Tests\Unit;

use App\Models\GroupRide;
use App\Services\AirQualityService;
use App\Services\TimezoneService;
use App\Services\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenMeteoFailureCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.open_meteo.retry_attempts' => 0]);
    }

    private function ride(): GroupRide
    {
        $ride = new GroupRide([
            'meeting_point_lat' => -37.8136,
            'meeting_point_lng' => 144.9631,
        ]);
        $ride->ride_date = Carbon::parse('2026-11-01 09:30:00', 'UTC');

        return $ride;
    }

    public function test_weather_failures_are_not_retried_on_every_request(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $service = new WeatherService;

        $this->assertNull($service->forRide($this->ride()));
        $sent = count(Http::recorded());

        $this->assertNull($service->forRide($this->ride()));
        $this->assertSame($sent, count(Http::recorded()));
    }

    public function test_air_quality_failures_are_not_retried_on_every_request(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $service = new AirQualityService;

        $this->assertNull($service->forRide($this->ride()));
        $sent = count(Http::recorded());

        $this->assertNull($service->forRide($this->ride()));
        $this->assertSame($sent, count(Http::recorded()));
    }

    public function test_timezone_failures_are_not_retried_on_every_request(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $service = new TimezoneService;

        $this->assertNull($service->forCoordinates(-37.8136, 144.9631));
        $sent = count(Http::recorded());

        $this->assertNull($service->forCoordinates(-37.8136, 144.9631));
        $this->assertSame($sent, count(Http::recorded()));
    }

    public function test_successful_timezone_lookups_are_still_cached(): void
    {
        Http::fake(['*' => Http::response(['timezone' => 'Australia/Melbourne'], 200)]);
        $service = new TimezoneService;

        $this->assertSame('Australia/Melbourne', $service->forCoordinates(-37.8136, 144.9631));
        $this->assertSame('Australia/Melbourne', $service->forCoordinates(-37.8136, 144.9631));
        Http::assertSentCount(1);
    }
}

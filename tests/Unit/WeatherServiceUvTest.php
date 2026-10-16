<?php

namespace Tests\Unit;

use App\Models\GroupRide;
use App\Models\Route;
use App\Services\WeatherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherServiceUvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function ride(?int $estimatedTimeMin): GroupRide
    {
        $ride = new GroupRide([
            'title' => 'Test Ride',
            'meeting_point_lat' => -37.8136,
            'meeting_point_lng' => 144.9631,
            'status' => 'planned',
            'version' => 1,
        ]);
        $ride->ride_date = Carbon::parse('2026-11-01 09:30:00', 'UTC');

        $route = new Route(['estimated_time_min' => $estimatedTimeMin]);
        $ride->setRelation('route', $route);

        return $ride;
    }

    private function fakeForecast(): void
    {
        $hours = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00'];
        $uv = [1, 3, 7, 9, 5, 2];

        Http::fake([
            '*' => Http::response([
                'hourly' => [
                    'time' => array_map(
                        fn ($h) => Carbon::parse("2026-11-01 {$h}:00", 'UTC')->getTimestamp(),
                        $hours
                    ),
                    'temperature_2m' => array_fill(0, 6, 20),
                    'apparent_temperature' => array_fill(0, 6, 20),
                    'precipitation_probability' => array_fill(0, 6, 0),
                    'weather_code' => array_fill(0, 6, 0),
                    'wind_speed_10m' => array_fill(0, 6, 10),
                    'wind_gusts_10m' => array_fill(0, 6, 15),
                    'uv_index' => $uv,
                ],
                'daily' => [
                    'time' => [Carbon::parse('2026-11-01 00:00:00', 'UTC')->getTimestamp()],
                    'sunrise' => [Carbon::parse('2026-11-01 06:00:00', 'UTC')->getTimestamp()],
                    'sunset' => [Carbon::parse('2026-11-01 20:00:00', 'UTC')->getTimestamp()],
                ],
            ]),
        ]);
    }

    public function test_it_reports_the_peak_uv_across_the_ride_duration(): void
    {
        $this->fakeForecast();

        $weather = (new WeatherService)->forRide($this->ride(180));

        $this->assertNotNull($weather);
        $this->assertSame(9.0, $weather['uv_index']);
        $this->assertSame('Very high', $weather['uv_level']);
    }

    public function test_it_falls_back_to_the_start_hour_without_a_duration(): void
    {
        $this->fakeForecast();

        $weather = (new WeatherService)->forRide($this->ride(null));

        $this->assertNotNull($weather);
        $this->assertSame(3.0, $weather['uv_index']);
        $this->assertSame('Moderate', $weather['uv_level']);
    }

    public function test_a_short_ride_only_considers_its_start_hour(): void
    {
        $this->fakeForecast();

        $weather = (new WeatherService)->forRide($this->ride(30));

        $this->assertNotNull($weather);
        $this->assertSame(3.0, $weather['uv_index']);
    }
}

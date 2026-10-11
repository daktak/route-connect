<?php

namespace Tests\Unit;

use App\Services\GpxParser;
use PHPUnit\Framework\TestCase;

class GpxParserTest extends TestCase
{
    private function gpx(string $points): string
    {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="test">
          <trk>
            <name>Test Track</name>
            <trkseg>
              {$points}
            </trkseg>
          </trk>
        </gpx>
        XML;
    }

    public function test_it_keeps_elevation_when_present(): void
    {
        $parsed = (new GpxParser)->parse($this->gpx(
            '<trkpt lat="1.0" lon="2.0"><ele>100.5</ele></trkpt>'.
            '<trkpt lat="1.1" lon="2.1"><ele>110.0</ele></trkpt>'
        ));

        $points = $parsed['gpx_data']['tracks'][0]['segments'][0];

        $this->assertSame(100.5, $points[0][2]);
        $this->assertSame(110.0, $points[1][2]);
        $this->assertSame(10, $parsed['elevation_gain_m']);
    }

    public function test_it_stores_null_elevation_when_absent(): void
    {
        $parsed = (new GpxParser)->parse($this->gpx(
            '<trkpt lat="1.0" lon="2.0"></trkpt>'.
            '<trkpt lat="1.1" lon="2.1"></trkpt>'
        ));

        $points = $parsed['gpx_data']['tracks'][0]['segments'][0];

        $this->assertNull($points[0][2]);
        $this->assertNull($points[1][2]);
        $this->assertSame(0, $parsed['elevation_gain_m']);
    }

    public function test_elevation_gain_ignores_points_without_elevation(): void
    {
        $parsed = (new GpxParser)->parse($this->gpx(
            '<trkpt lat="1.0" lon="2.0"><ele>100.0</ele></trkpt>'.
            '<trkpt lat="1.1" lon="2.1"></trkpt>'.
            '<trkpt lat="1.2" lon="2.2"><ele>150.0</ele></trkpt>'
        ));

        $this->assertSame(0, $parsed['elevation_gain_m']);
    }
}

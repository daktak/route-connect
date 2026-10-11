<?php

namespace Tests\Unit;

use App\Services\GpxGenerator;
use PHPUnit\Framework\TestCase;

class GpxGeneratorTest extends TestCase
{
    public function test_it_generates_a_route_with_elevation(): void
    {
        $gpxData = [
            'tracks' => [[
                'name' => 'Original Track',
                'segments' => [
                    [
                        [145.1, -37.8, 120.5],
                        [145.2, -37.9, null],
                        [145.3, -38.0, 130.0],
                    ],
                ],
            ]],
        ];

        $xml = (new GpxGenerator)->generateFromGpxData($gpxData, 'My Route');

        $this->assertStringNotContainsString('<trk>', $xml);
        $this->assertStringNotContainsString('<trkpt', $xml);

        $doc = new \SimpleXMLElement($xml);
        $doc->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

        $routes = $doc->xpath('//gpx:rte');
        $this->assertCount(1, $routes);
        $this->assertSame('My Route', (string) $routes[0]->name);

        $points = $doc->xpath('//gpx:rte/gpx:rtept');
        $this->assertCount(3, $points);

        $this->assertSame('145.1', (string) $points[0]['lon']);
        $this->assertSame('-37.8', (string) $points[0]['lat']);
        $this->assertSame('120.5', (string) $points[0]->ele);

        $this->assertFalse(isset($points[1]->ele));

        $this->assertSame('130', (string) $points[2]->ele);
    }

    public function test_it_flattens_tracks_and_segments_into_a_single_route(): void
    {
        $gpxData = [
            'tracks' => [
                [
                    'name' => 'First',
                    'segments' => [
                        [[145.0, -37.0, 10.0], [145.1, -37.1, 20.0]],
                        [[145.2, -37.2, 30.0]],
                    ],
                ],
                [
                    'name' => 'Second',
                    'segments' => [
                        [[145.3, -37.3, 40.0]],
                    ],
                ],
            ],
        ];

        $xml = (new GpxGenerator)->generateFromGpxData($gpxData, 'Merged');

        $doc = new \SimpleXMLElement($xml);
        $doc->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

        $this->assertCount(1, $doc->xpath('//gpx:rte'));
        $this->assertCount(4, $doc->xpath('//gpx:rte/gpx:rtept'));
    }
}

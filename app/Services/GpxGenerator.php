<?php

namespace App\Services;

class GpxGenerator
{
    public function generateFromGpxData(array $gpxData, string $name = 'Route'): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="Route Connect"></gpx>');

        $trk = $xml->addChild('trk');
        $trk->addChild('name', htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8'));

        foreach (($gpxData['tracks'] ?? []) as $track) {
            foreach (($track['segments'] ?? []) as $segment) {
                $trkseg = $trk->addChild('trkseg');
                foreach ($segment as $point) {
                    $lon = $point[0] ?? $point['lon'] ?? null;
                    $lat = $point[1] ?? $point['lat'] ?? null;
                    if ($lon === null || $lat === null) {
                        continue;
                    }
                    $trkpt = $trkseg->addChild('trkpt');
                    $trkpt->addAttribute('lat', (string) $lat);
                    $trkpt->addAttribute('lon', (string) $lon);
                }
            }
        }

        // Format with line breaks
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }
}

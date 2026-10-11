<?php

namespace Tests\Feature;

use App\Http\Controllers\RouteController;
use App\Models\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RouteDownloadTest extends TestCase
{
    public function test_gpx_download_uses_octet_stream_and_gpx_filename(): void
    {
        Storage::fake('local');

        $path = 'gpx/test.gpx';
        Storage::disk('local')->put($path, '<?xml version="1.0"?><gpx></gpx>');

        $route = new Route([
            'name' => 'HSS Loop',
            'gpx_file_path' => $path,
        ]);

        $response = (new RouteController)->download($route);

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('HSS Loop.gpx', $response->headers->get('Content-Disposition'));
    }
}

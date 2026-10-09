<?php

namespace App\Http\Controllers;

use App\Services\GpxParser;
use Illuminate\Http\Request;

class GpxController extends Controller
{
    public function parse(Request $request)
    {
        $request->validate([
            'gpx_file' => 'required|file|mimes:gpx|max:10240',
        ]);

        $file = $request->file('gpx_file');
        $content = file_get_contents($file->getRealPath());

        $parser = new GpxParser;
        $parsed = $parser->parse($content);

        // Add preview geometry for map
        $parsed['geometry'] = $parsed['geometry'] ?? null;

        return response()->json($parsed);
    }
}

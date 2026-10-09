<?php

namespace App\Http\Controllers;

use App\Services\GpxParser;
use Illuminate\Http\Request;

class GpxController extends Controller
{
    public function parse(Request $request)
    {
        $request->validate([
            'gpx_file' => 'required|file|max:10240|extensions:gpx',
        ]);

        $file = $request->file('gpx_file');
        $content = file_get_contents($file->getRealPath());

        $parser = new GpxParser;

        try {
            $parsed = $parser->parse($content);
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => 'The uploaded file is not a valid GPX file.'], 422);
        }

        return response()->json($parsed);
    }
}

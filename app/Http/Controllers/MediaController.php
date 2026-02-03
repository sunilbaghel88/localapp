<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(Request $request, string $path)
    {
        $path = ltrim($path, '/');

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $response = Storage::disk('public')->response($path);

        $origin = $request->headers->get('Origin', '*');
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}


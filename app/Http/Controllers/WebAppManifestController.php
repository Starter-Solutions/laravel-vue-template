<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebAppManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config()->string('app.name'),
            'id' => '/',
            'start_url' => route('dashboard', absolute: false),
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#ffffff',
            'icons' => [
                ['src' => '/pwa-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/pwa-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAppManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $prefersDark = $request->query('theme') === 'dark';
        $iconSuffix = $prefersDark ? '-dark' : '';
        $themeColor = $prefersDark ? '#0a0a0a' : '#ffffff';

        return response()->json([
            'name' => config()->string('app.name'),
            'id' => '/',
            'start_url' => route('dashboard', absolute: false),
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => $themeColor,
            'theme_color' => $themeColor,
            'icons' => [
                ['src' => "/pwa-icon-192{$iconSuffix}.png", 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => "/pwa-icon-512{$iconSuffix}.png", 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }
}

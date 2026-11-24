<?php

test('serves an installable app manifest to guests using the configured name', function () {
    config(['app.name' => 'Example Workspace']);

    $response = $this->get('/manifest.webmanifest');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPaths([
            'name' => 'Example Workspace',
            'id' => '/',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'icons.0.src' => '/pwa-icon-192.png',
            'icons.0.sizes' => '192x192',
            'icons.1.src' => '/pwa-icon-512.png',
            'icons.1.sizes' => '512x512',
        ]);
});

test('serves dark app icons with the same installation identity in dark mode', function () {
    $response = $this->get('/manifest.webmanifest?theme=dark');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPaths([
            'id' => '/',
            'start_url' => '/dashboard',
            'theme_color' => '#0a0a0a',
            'background_color' => '#0a0a0a',
            'icons.0.src' => '/pwa-icon-192-dark.png',
            'icons.1.src' => '/pwa-icon-512-dark.png',
        ]);
});

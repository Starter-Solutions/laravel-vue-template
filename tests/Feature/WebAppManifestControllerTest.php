<?php

test('serves an installable app manifest to guests using the configured name', function () {
    config(['app.name' => 'Example Workspace']);

    $response = $this->get('/manifest.webmanifest');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPaths([
            'name' => 'Example Workspace',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'icons.0.sizes' => '192x192',
            'icons.1.sizes' => '512x512',
        ]);
});

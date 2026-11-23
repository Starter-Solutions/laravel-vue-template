<?php

use Inertia\Testing\AssertableInertia as Assert;

test('an unknown browser URL renders the 404 page', function () {
    $this->get('/missing-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('NotFound'));
});

test('an unknown Inertia URL returns the 404 page without leaving Inertia', function () {
    $this->get('/missing-page', ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
        ->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'NotFound');
});

test('missing API URLs still return a JSON 404', function () {
    $this->get('/api/missing-page', ['Accept' => 'text/html'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

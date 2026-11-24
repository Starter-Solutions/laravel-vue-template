<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('links the app manifest from the home page', function () {
    $response = $this->get(route('home'));

    $response->assertOk()->assertSee('<link rel="manifest" href="/manifest.webmanifest">', escape: false);
});

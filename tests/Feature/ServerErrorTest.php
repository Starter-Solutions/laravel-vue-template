<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Exceptions::fake();

    $fail = function (): never {
        throw new RuntimeException('Example exception details');
    };

    Route::get('/server-error-test', $fail)->middleware('web');
    Route::get('/api/server-error-test', $fail);
});

test('production browser requests render the 500 page without exception details', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);

    $this->get('/server-error-test')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page->component('ServerError'))
        ->assertDontSee('Example exception details');
});

test('production Inertia requests render the 500 page', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);
    $request = Request::create('/server-error-test');
    $version = app(HandleInertiaRequests::class)->version($request);

    $this->get('/server-error-test', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version ?? '',
        'Accept' => 'text/html, application/xhtml+xml',
    ])
        ->assertStatus(500)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'ServerError');
});

test('local requests still show the exception details in debug mode', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['app.debug' => true]);

    $this->get('/server-error-test')
        ->assertStatus(500)
        ->assertSee('Example exception details');
});

test('production API errors still return a JSON 500', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);

    $this->get('/api/server-error-test', ['Accept' => 'text/html'])
        ->assertStatus(500)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('component');
});

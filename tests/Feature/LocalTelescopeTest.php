<?php

use Illuminate\Database\Migrations\Migrator;

test('telescope dashboard is unavailable outside the local environment', function () {
    $this->get('/telescope')->assertNotFound();
});

test('local only migrations are excluded outside the local environment', function () {
    expect(app(Migrator::class)->paths())
        ->not->toContain(database_path('migrations/local'));
});

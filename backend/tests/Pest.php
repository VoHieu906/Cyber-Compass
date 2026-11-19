<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the full application against the MySQL testing database
| (cybercompass_testing), refreshed per test.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Requests come from the SPA origin, so Sanctum treats them as stateful (session cookie auth).
        $this->withHeaders([
            'Origin' => config('app.frontend_url'),
            'Referer' => config('app.frontend_url').'/',
        ]);
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

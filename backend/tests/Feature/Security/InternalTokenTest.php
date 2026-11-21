<?php

use App\Models\User;

/*
| The Next.js server's public reads all come from one address on AWS (SECURITY_REVIEW.md S8).
| With the shared token they skip the per-address `api` limit; nothing else does.
*/

beforeEach(fn () => config(['cybercompass.internal_api_token' => str_repeat('t', 40)]));

function hammer(object $test, array $headers, int $times = 125): int
{
    $last = 200;
    for ($i = 0; $i < $times; $i++) {
        $last = $test->withHeaders($headers)->getJson('/api/v1/domains')->status();
    }

    return $last;
}

it('limits guests from one address to 120 requests a minute', function () {
    expect(hammer($this, []))->toBe(429);
});

it('lets the web server read public data without the per-address limit', function () {
    expect(hammer($this, ['X-Internal-Token' => str_repeat('t', 40)]))->toBe(200);
});

it('does not exempt a wrong token', function () {
    expect(hammer($this, ['X-Internal-Token' => str_repeat('x', 40)]))->toBe(429);
});

it('exempts nothing when no token is configured', function () {
    config(['cybercompass.internal_api_token' => null]);

    expect(hammer($this, ['X-Internal-Token' => '']))->toBe(429);
});

it('keeps signed-in learners on their own limit even with the token', function () {
    $this->actingAs(User::factory()->create());

    expect(hammer($this, ['X-Internal-Token' => str_repeat('t', 40)]))->toBe(429);
});

<?php

use Illuminate\Http\Request;

/*
| Behind the ALB (D41) the request comes from the load balancer, which appends the real client to
| X-Forwarded-For. Laravel trusts only that immediate peer, so the address used by the per-address
| limits is the one the ALB added; a client cannot escape them by sending its own header.
*/

const ALB = '10.0.1.25';
const CLIENT = '203.0.113.9';

it('limits the real client even when it forges X-Forwarded-For', function () {
    foreach (range(1, 20) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => ALB])
            ->withHeader('X-Forwarded-For', "198.51.100.{$i}, ".CLIENT)
            ->postJson('/api/v1/auth/login', ['email' => "forged{$i}@example.com", 'password' => 'wrong-pass-1'])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => ALB])
        ->withHeader('X-Forwarded-For', '198.51.100.99, '.CLIENT)
        ->postJson('/api/v1/auth/login', ['email' => 'forged99@example.com', 'password' => 'wrong-pass-1'])
        ->assertStatus(429);
});

it('keeps different clients behind the same load balancer apart', function () {
    foreach (range(1, 20) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => ALB])
            ->withHeader('X-Forwarded-For', CLIENT)
            ->postJson('/api/v1/auth/login', ['email' => "a{$i}@example.com", 'password' => 'wrong-pass-1']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => ALB])
        ->withHeader('X-Forwarded-For', '203.0.113.200')
        ->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'wrong-pass-1'])
        ->assertUnprocessable();
});

it('takes the address the load balancer added, not one the client wrote', function () {
    $request = null;
    $this->app['router']->middleware('api')->get('/api/v1/__client-address', function (Request $r) use (&$request) {
        $request = $r;

        return ['ok' => true];
    });

    $this->withServerVariables(['REMOTE_ADDR' => ALB])
        ->withHeaders(['X-Forwarded-For' => '198.51.100.1, '.CLIENT, 'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https'])
        ->getJson('/api/v1/__client-address')
        ->assertOk();

    expect($request->ip())->toBe(CLIENT)
        ->and($request->getHost())->not->toBe('evil.example')
        ->and($request->isSecure())->toBeTrue();
});

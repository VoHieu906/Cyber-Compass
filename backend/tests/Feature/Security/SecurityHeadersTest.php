<?php

use App\Http\Middleware\SecurityHeaders;

it('sends the API security headers on responses, errors included', function (string $method, string $uri, int $status) {
    $response = $this->json($method, $uri)->assertStatus($status);

    foreach (SecurityHeaders::HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }
})->with([
    'public read' => ['GET', '/api/v1/health', 200],
    'guest on a protected route' => ['GET', '/api/v1/me', 401],
    'unknown route' => ['GET', '/api/v1/no-such-route', 404],
    'validation error' => ['POST', '/api/v1/auth/login', 422],
]);

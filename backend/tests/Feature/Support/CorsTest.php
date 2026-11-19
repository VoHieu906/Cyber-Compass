<?php

it('allows credentialed requests from the frontend origin', function () {
    $origin = config('app.frontend_url');

    $this->call('OPTIONS', '/api/v1/health', server: [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-xsrf-token, content-type',
    ])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('does not allow other origins', function () {
    $response = $this->call('OPTIONS', '/api/v1/health', server: [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    // With a single allowed origin the header is static; the browser rejects the mismatch.
    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example');
});

it('issues the XSRF cookie for the SPA', function () {
    $this->get('/sanctum/csrf-cookie', ['Origin' => config('app.frontend_url'), 'Referer' => config('app.frontend_url').'/'])
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

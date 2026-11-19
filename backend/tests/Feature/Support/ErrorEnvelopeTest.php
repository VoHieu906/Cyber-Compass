<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Every API error uses the envelope in docs/API_SPECIFICATION.md#errors.
*/

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::post('validate', fn (Request $r) => $r->validate(['email' => ['required', 'email']]));
        Route::get('auth', fn () => 'secret')->middleware('auth:sanctum');
        Route::get('boom', fn () => throw new RuntimeException('database password is hunter2'));
        Route::get('forbidden', fn () => abort(403));
    });
});

it('returns 404 with the envelope for unknown API routes', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.', 'code' => 'not_found']);
});

it('returns 422 with field errors for validation failures', function () {
    $this->postJson('/api/v1/_test/validate', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonStructure(['message', 'code', 'errors' => ['email']]);
});

it('returns 401 for unauthenticated requests to protected routes', function () {
    $this->getJson('/api/v1/_test/auth')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
});

it('returns 403 with the forbidden code', function () {
    $this->getJson('/api/v1/_test/forbidden')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

it('returns 405 for a wrong method', function () {
    $this->deleteJson('/api/v1/health')
        ->assertStatus(405)
        ->assertJsonPath('code', 'method_not_allowed');
});

it('never leaks internals on server errors when debug is off', function () {
    config(['app.debug' => false]);

    $this->getJson('/api/v1/_test/boom')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Something went wrong on our side.', 'code' => 'server_error'])
        ->assertDontSee('hunter2');
});

it('renders the envelope for API paths even without an Accept header', function () {
    $this->get('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

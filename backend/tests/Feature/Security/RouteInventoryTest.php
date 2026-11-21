<?php

use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| Every API route, read from the router (docs/SECURITY_REVIEW.md). A new route fails here until it
| is either listed as public or sits behind auth:sanctum, and the routes that run expensive or
| abusable work keep their own limiter.
*/

/** Routes a guest may call. Everything else under api/ requires a session. */
const PUBLIC_API_ROUTES = [
    'GET api/v1/health',
    'POST api/v1/auth/login',
    'POST api/v1/auth/register',
    'POST api/v1/auth/forgot-password',
    'POST api/v1/auth/reset-password',
    'GET api/v1/domains',
    'GET api/v1/courses',
    'GET api/v1/courses/{slug}',
    'GET api/v1/careers',
    'GET api/v1/careers/{slug}',
    'GET api/v1/search',
];

/** Route-specific limiters on top of the general `api` limiter (120 a minute per user). */
const ROUTE_LIMITERS = [
    'POST api/v1/auth/login' => 'throttle:login',
    'POST api/v1/auth/register' => 'throttle:register',
    'POST api/v1/auth/forgot-password' => 'throttle:password',
    'POST api/v1/auth/reset-password' => 'throttle:password',
    'PUT api/v1/me/password' => 'throttle:password',
    'POST api/v1/me/avatar' => 'throttle:uploads',
    'POST api/v1/admin/content/media' => 'throttle:uploads',
    'POST api/v1/lab-attempts/{id}/actions' => 'throttle:lab-actions',
    'POST api/v1/lab-attempts/{id}/reset' => 'throttle:lab-actions',
    'POST api/v1/lessons/{slug}/exercise/attempts' => 'throttle:exercise-attempts',
    'GET api/v1/search' => 'throttle:search',
];

/** @return array<string, RouteDefinition> keyed "METHOD uri" */
function apiRoutes(): array
{
    $routes = [];
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }
        $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
        $routes["{$method} {$route->uri()}"] = $route;
    }

    return $routes;
}

it('lists every public route on purpose and puts the rest behind a session', function () {
    $routes = apiRoutes();
    expect(count($routes))->toBeGreaterThan(80);

    foreach (PUBLIC_API_ROUTES as $key) {
        expect($routes)->toHaveKey($key);
    }
    foreach ($routes as $key => $route) {
        $middleware = $route->gatherMiddleware();
        expect($middleware)->toContain('api');
        if (! in_array($key, PUBLIC_API_ROUTES, true)) {
            $this->assertContains('auth:sanctum', $middleware, "{$key} is not public but has no auth:sanctum");
        }
    }
});

it('keeps every admin route behind the admin check and its limiter', function () {
    $admin = array_filter(apiRoutes(), fn ($r, $key) => str_contains($key, ' api/v1/admin/'), ARRAY_FILTER_USE_BOTH);
    expect(count($admin))->toBeGreaterThan(20);

    foreach ($admin as $key => $route) {
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware, $key);
        $this->assertContains('throttle:admin', $middleware, $key);
        $this->assertContains(EnsureAdmin::class, $middleware, $key);
    }
});

it('keeps the route-specific limiters', function () {
    $routes = apiRoutes();
    foreach (ROUTE_LIMITERS as $key => $limiter) {
        expect($routes)->toHaveKey($key);
        $this->assertContains($limiter, $routes[$key]->gatherMiddleware(), "{$key} lost {$limiter}");
    }
});

it('answers a guest with 401 on every route that is not public', function () {
    foreach (apiRoutes() as $key => $route) {
        if (in_array($key, PUBLIC_API_ROUTES, true)) {
            continue;
        }
        [$method] = explode(' ', $key);
        // A value each parameter's constraint accepts, so the request reaches the middleware.
        $value = fn (array $m) => match (true) {
            str_contains($route->wheres[$m[1]] ?? '', 'a-f') => (string) Str::uuid(),
            str_contains($m[1], 'slug') => 'x',
            default => '1',
        };
        $uri = '/'.preg_replace_callback('/\{([a-z_]+)(?::[a-z_]+)?\}/i', $value, $route->uri());

        $this->assertSame(401, $this->json($method, $uri)->status(), "{$key} answered a guest with something other than 401");
    }
});

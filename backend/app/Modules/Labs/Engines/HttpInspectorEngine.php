<?php

namespace App\Modules\Labs\Engines;

/**
 * Inspect and replay HTTP against a simulated web app (ARCHITECTURE §7). Requests never leave
 * the server: each one is matched against the scenario's routes, which answer with canned
 * responses. The learner sees recorded exchanges and their own session cookie; the routes stay
 * hidden.
 *
 * Scenario: app {name, origin}, session {name, value} (the learner's own cookie), captured
 * [{request, response}], routes [{id, method, path, cookie? {name, value}, response,
 * unauthorized?}], checkpoints. Checkpoint kind `request`: the learner received a 2xx from
 * route {route}.
 */
final class HttpInspectorEngine implements LabEngine
{
    private const METHODS = ['GET', 'POST', 'PUT', 'DELETE'];

    private const MAX_PATH = 200;

    private const MAX_PAIRS = 10;

    private const MAX_VALUE = 500;

    private const MAX_BODY = 2000;

    private const SENT_KEPT = 20;

    public function key(): string
    {
        return 'http-inspector';
    }

    public function checkpointKinds(): array
    {
        return ['request'];
    }

    public function validateScenario(array $scenario): array
    {
        $errors = [];
        $ids = [];
        foreach ($scenario['routes'] as $i => $route) {
            if (in_array($route['id'], $ids, true)) {
                $errors["routes.{$i}.id"][] = 'duplicate route id';
            }
            $ids[] = $route['id'];
        }
        foreach ($scenario['checkpoints'] as $i => $checkpoint) {
            if ($checkpoint['kind'] === 'request' && ! in_array($checkpoint['route'], $ids, true)) {
                $errors["checkpoints.{$i}.route"][] = 'route must be one of the route ids';
            }
        }

        return $errors;
    }

    public function initialState(array $scenario): array
    {
        return ['sent' => [], 'hits' => []];
    }

    public function apply(array $scenario, array $state, array $action): array
    {
        if (($action['type'] ?? null) !== 'send') {
            throw new LabActionRejected('Unknown action. Use send.');
        }
        $request = $this->request($action['request'] ?? null);
        [$response, $routeId] = $this->respond($scenario, $request);

        if ($routeId !== null && $response['status'] >= 200 && $response['status'] < 300) {
            $state['hits'] = array_values(array_unique([...$state['hits'], $routeId]));
        }
        $state['sent'] = array_slice([...$state['sent'], ['request' => $request, 'status' => $response['status']]], -self::SENT_KEPT);

        return ['state' => $state, 'output' => ['response' => $response]];
    }

    public function checkpointPassed(array $scenario, array $state, array $checkpoint): bool
    {
        return in_array($checkpoint['route'], $state['hits'] ?? [], true);
    }

    public function publicScenario(array $scenario): array
    {
        return [
            'app' => $scenario['app'],
            'session' => $scenario['session'],
            'captured' => $scenario['captured'] ?? [],
            'methods' => self::METHODS,
        ];
    }

    public function view(array $scenario, array $state): array
    {
        return ['sent' => $state['sent']];
    }

    /** @return array{0: array<string, mixed>, 1: ?string} the response, and the route that answered */
    private function respond(array $scenario, array $request): array
    {
        foreach ($scenario['routes'] as $route) {
            if ($route['method'] !== $request['method'] || $route['path'] !== $request['path']) {
                continue;
            }
            $cookie = $route['cookie'] ?? null;
            if ($cookie && ($request['cookies'][$cookie['name']] ?? null) !== $cookie['value']) {
                return [$route['unauthorized'] ?? ['status' => 401, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"error":"Sign in first."}'], null];
            }

            return [$route['response'], $route['id']];
        }

        return [$scenario['not_found'] ?? ['status' => 404, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'Not Found'], null];
    }

    /** @return array{method: string, path: string, headers: array<string, string>, cookies: array<string, string>, body: string} */
    private function request(mixed $request): array
    {
        if (! is_array($request) || ! in_array($request['method'] ?? null, self::METHODS, true)) {
            throw new LabActionRejected('The method must be one of '.implode(', ', self::METHODS).'.');
        }
        $path = $request['path'] ?? null;
        if (! is_string($path) || ! str_starts_with($path, '/') || strlen($path) > self::MAX_PATH || preg_match('/[\s\x00-\x1F]/', $path)) {
            throw new LabActionRejected('The path starts with / and has no spaces (at most '.self::MAX_PATH.' characters).');
        }
        $pairs = function (mixed $list, string $what): array {
            if ($list === null) {
                return [];
            }
            if (! is_array($list) || count($list) > self::MAX_PAIRS) {
                throw new LabActionRejected('At most '.self::MAX_PAIRS." {$what}.");
            }
            $clean = [];
            foreach ($list as $name => $value) {
                if (! is_string($name) || ! preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name) || ! is_string($value) || strlen($value) > self::MAX_VALUE) {
                    throw new LabActionRejected("Each of the {$what} needs a name (letters, digits, - _ .) and a value of at most ".self::MAX_VALUE.' characters.');
                }
                $clean[$name] = $value;
            }

            return $clean;
        };
        $body = $request['body'] ?? '';
        if (! is_string($body) || strlen($body) > self::MAX_BODY) {
            throw new LabActionRejected('The body may hold at most '.self::MAX_BODY.' characters.');
        }

        return [
            'method' => $request['method'],
            'path' => $path,
            'headers' => $pairs($request['headers'] ?? null, 'headers'),
            'cookies' => $pairs($request['cookies'] ?? null, 'cookies'),
            'body' => $body,
        ];
    }
}

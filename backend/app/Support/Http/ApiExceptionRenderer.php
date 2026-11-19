<?php

namespace App\Support\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception on an API route as the error envelope documented in
 * docs/API_SPECIFICATION.md#errors: { message, code, errors? }.
 * Internals never reach the client unless APP_DEBUG is on.
 */
final class ApiExceptionRenderer
{
    /** Status code => stable, machine-readable error code. */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        419 => 'csrf_mismatch',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        503 => 'service_unavailable',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        [$status, $message, $headers] = $this->describe($e);

        $body = [
            'message' => $message,
            'code' => self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'error'),
        ];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->errors();
        }

        if ($status >= 500 && config('app.debug')) {
            $body['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'location' => $e->getFile().':'.$e->getLine(),
            ];
        }

        return new JsonResponse($body, $status, $headers);
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, string>}
     */
    private function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [422, __('The given data was invalid.'), []],
            $e instanceof AuthenticationException => [401, __('Unauthenticated.'), []],
            $e instanceof AuthorizationException => [403, __('This action is unauthorized.'), []],
            // Another user's record is reported as missing, not forbidden, so ids never leak.
            $e instanceof ModelNotFoundException => [404, __('Resource not found.'), []],
            $e instanceof TokenMismatchException => [419, __('Your session has expired. Refresh and try again.'), []],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $this->httpMessage($e),
                $e->getHeaders(),
            ],
            default => [500, __('Something went wrong on our side.'), []],
        };
    }

    private function httpMessage(HttpExceptionInterface $e): string
    {
        return match ($e->getStatusCode()) {
            404 => __('Resource not found.'),
            405 => __('Method not allowed.'),
            429 => __('Too many requests. Try again shortly.'),
            419 => __('Your session has expired. Refresh and try again.'),
            default => $e->getStatusCode() >= 500
                ? __('Something went wrong on our side.')
                : ($e->getMessage() !== '' ? $e->getMessage() : __('Request failed.')),
        };
    }
}

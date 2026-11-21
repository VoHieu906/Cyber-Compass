<?php

use App\Http\Middleware\RejectSuspendedUsers;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetRequestLocale;
use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use App\Support\Http\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Module console commands (content:import, cybercompass:make-admin, …).
    ->withCommands([
        __DIR__.'/../app/Modules/Content/Console',
        __DIR__.'/../app/Modules/Admin/Console',
        __DIR__.'/../app/Modules/Progress/Console',
        __DIR__.'/../app/Modules/Notifications/Console',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA authentication: session cookies + CSRF for requests from the frontend origin.
        // Security headers on every response, including errors for unknown routes (D42).
        $middleware->append(SecurityHeaders::class);
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        $middleware->appendToGroup('api', [SetRequestLocale::class, RejectSuspendedUsers::class]);
        // Admin routes check the role before route model binding, so a learner always gets 403
        // and can't learn which lesson or user ids exist from 404s.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureAdmin::class);
        // Lesson content is stored exactly as authored: leading spaces in code blocks matter, and an
        // empty text must stay "" so the schema can say it is empty. Each block type's normalizer
        // trims what should be trimmed (Content\Blocks).
        $contentApi = fn (Request $request) => $request->is('api/v1/admin/content/*');
        $middleware->trimStrings(except: [$contentApi]);
        $middleware->convertEmptyStringsToNull(except: [$contentApi]);
        // Behind the AWS ALB, trust only the immediate peer (the load balancer: D40 lets nothing else
        // reach the tasks) and only the headers the ALB sets. '*' would trust every address, so a
        // client could pick its own X-Forwarded-For and escape the per-address limits, and
        // X-Forwarded-Host is never trusted (docs/SECURITY_REVIEW.md S9).
        $middleware->trustProxies(at: 'REMOTE_ADDR', headers: Request::HEADER_X_FORWARDED_AWS_ELB);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(new ApiExceptionRenderer);
    })->create();

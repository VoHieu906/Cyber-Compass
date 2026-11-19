<?php

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\System\Services\HealthCheck;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    /**
     * Service health.
     *
     * Reports API status, database reachability and the deployed version.
     * Always 200 while the API process is up; `status` is `degraded` when a dependency is down.
     *
     * @unauthenticated
     */
    public function __invoke(HealthCheck $health): JsonResponse
    {
        return response()->json(['data' => $health->run()]);
    }
}

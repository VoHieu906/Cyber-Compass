<?php

namespace App\Modules\Roadmap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Roadmap\Services\KnowledgeMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KnowledgeMapController extends Controller
{
    /**
     * My knowledge map.
     *
     * Every topic with a lesson I can study, by domain and level, with my status, prerequisite
     * edges and whether it is on my roadmap; each domain carries my latest assessment score and
     * band and my lesson completion. Needs no career goal.
     */
    public function __invoke(Request $request, KnowledgeMap $map): JsonResponse
    {
        return response()->json(['data' => $map->present($request->user())]);
    }
}

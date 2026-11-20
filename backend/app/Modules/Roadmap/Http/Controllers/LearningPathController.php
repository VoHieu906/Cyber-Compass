<?php

namespace App\Modules\Roadmap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Modules\Roadmap\Actions\GenerateLearningPath;
use App\Modules\Roadmap\Services\PathProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** My roadmap. Every route acts on the signed-in learner; there is no id to address anyone else's. */
final class LearningPathController extends Controller
{
    /**
     * My active roadmap.
     *
     * Topics with computed status, the reason each is there, lessons with status, prerequisite
     * edges, completion, the next lesson, a time estimate, and `outdated` reasons when my career
     * goal or latest assessment has changed since it was built. `data` is null before the first one.
     */
    public function show(Request $request, PathProgress $progress): JsonResponse
    {
        $path = $request->user()->learningPaths()->active()->first();

        return response()->json(['data' => $path ? $progress->present($path, $request->user()) : null]);
    }

    /**
     * Build my roadmap.
     *
     * Uses my career goal and my latest submitted assessment, and archives the previous version.
     * 422 without a career goal.
     */
    public function generate(Request $request, GenerateLearningPath $generate, PathProgress $progress): JsonResponse
    {
        $path = $generate->handle($request->user());

        return response()->json(['data' => $progress->present($path, $request->user())], 201);
    }

    /** My roadmap versions, newest first. */
    public function index(Request $request): JsonResponse
    {
        $paths = $request->user()->learningPaths()
            ->with(['career:id,slug,title', 'attempt:id,score_percent'])
            ->withCount('nodes')
            ->orderByDesc('version')
            ->get();

        return response()->json(['data' => $paths->map(fn (LearningPath $p) => [
            'id' => $p->id,
            'version' => $p->version,
            'status' => $p->status,
            'generated_at' => $p->generated_at->toIso8601String(),
            'career' => ['slug' => $p->career->slug, 'title' => $p->career->title],
            'assessment_score' => $p->attempt?->score_percent,
            'topic_count' => $p->nodes_count,
        ])]);
    }
}

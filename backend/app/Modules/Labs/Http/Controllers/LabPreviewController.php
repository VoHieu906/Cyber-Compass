<?php

namespace App\Modules\Labs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Modules\Labs\Http\Requests\LabActionRequest;
use App\Modules\Labs\Services\LabPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * An admin plays a lab's working copy, draft included (D79). Same shapes as the learner API;
 * nothing is saved and no event fires: no attempt, activity, streak, notification or LabCompleted.
 */
final class LabPreviewController extends Controller
{
    /** Open (or continue) the preview: what `GET /labs/{slug}` returns, for the working copy. The attempt's `id` is 0. */
    public function show(Request $request, Lab $lab, LabPreview $preview): JsonResponse
    {
        Gate::authorize('preview', $lab);

        return response()->json(['data' => $preview->open($request->user(), $lab)]);
    }

    /** Apply one action. `version` is the preview state's version; 409 when it is stale or the draft changed. */
    public function act(LabActionRequest $request, Lab $lab, LabPreview $preview): JsonResponse
    {
        Gate::authorize('preview', $lab);

        return response()->json(['data' => $preview->act($request->user(), $lab, (int) $request->validated('version'), (array) $request->input('action'))]);
    }

    /** Start the preview over. */
    public function reset(Request $request, Lab $lab, LabPreview $preview): JsonResponse
    {
        Gate::authorize('preview', $lab);

        return response()->json(['data' => $preview->reset($request->user(), $lab)]);
    }
}

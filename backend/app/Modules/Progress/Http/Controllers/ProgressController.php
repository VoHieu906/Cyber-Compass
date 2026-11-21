<?php

namespace App\Modules\Progress\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Http\Resources\AttemptSummaryResource;
use App\Modules\Progress\Services\ProgressReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * The learner dashboard (Phase 6). Every figure is the signed-in learner's own; weeks and
 * streaks use their local date (D33).
 */
final class ProgressController extends Controller
{
    /** How many past assessments the history chart shows. */
    private const ASSESSMENT_HISTORY = 20;

    public function __construct(private readonly ProgressReport $report) {}

    /** Courses, lessons, total and weekly minutes, streak and roadmap completion. */
    public function summary(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->report->summary($request->user())]);
    }

    /**
     * Weekly minutes and lessons completed.
     *
     * `range` is a number of weeks with a `w` suffix, 4w to 52w (default 12w).
     */
    public function activity(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '12w');
        if (! preg_match('/^(\d{1,2})w$/', $range, $m) || (int) $m[1] < 4 || (int) $m[1] > ProgressReport::MAX_WEEKS) {
            throw ValidationException::withMessages(['range' => [__('The range must be between 4w and 52w.')]]);
        }

        return response()->json(['data' => $this->report->weekly($request->user(), (int) $m[1])]);
    }

    /** Latest assessment score and band, and lesson completion, per domain. */
    public function skills(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->report->skills($request->user())]);
    }

    /** The most recent learning activities, newest first. */
    public function recent(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->report->recent($request->user())]);
    }

    /** Up to three lessons to study next, from the roadmap or else from enrolled courses. */
    public function next(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->report->next($request->user())]);
    }

    /** Completion of every enrolled course. */
    public function courses(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->report->courses($request->user())]);
    }

    /** The last submitted assessments with overall and domain scores, oldest first. */
    public function assessments(Request $request): AnonymousResourceCollection
    {
        $attempts = $request->user()->assessmentAttempts()
            ->submitted()
            ->with(['assessment', 'answers.question.domain'])
            ->latest('submitted_at')
            ->latest('id')
            ->limit(self::ASSESSMENT_HISTORY)
            ->get()
            ->reverse()
            ->values();

        return AttemptSummaryResource::collection($attempts);
    }
}

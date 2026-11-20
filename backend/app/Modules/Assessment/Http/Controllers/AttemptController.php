<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Modules\Assessment\Actions\SaveAnswer;
use App\Modules\Assessment\Actions\SubmitAttempt;
use App\Modules\Assessment\Http\Requests\SaveAnswerRequest;
use App\Modules\Assessment\Http\Resources\AttemptResource;
use App\Modules\Assessment\Http\Resources\AttemptSummaryResource;
use App\Modules\Assessment\Services\AttemptResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AttemptController extends Controller
{
    /** My submitted attempts, newest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        return AttemptSummaryResource::collection(
            $request->user()->assessmentAttempts()
                ->submitted()
                ->with(['assessment', 'answers.question.domain'])
                ->latest('submitted_at')
                ->latest('id')
                ->paginate($perPage),
        );
    }

    /** An attempt with its questions and my saved answers, without the answer key. */
    public function show(AssessmentAttempt $attempt): AttemptResource
    {
        Gate::authorize('view', $attempt);

        return new AttemptResource(self::loadForTaking($attempt));
    }

    /**
     * Save one answer (autosave).
     *
     * `option_id` null clears it. 409 once the attempt is submitted; 404 for a question the
     * attempt does not contain; 422 for an option of another question.
     */
    public function answer(SaveAnswerRequest $request, AssessmentAttempt $attempt, int $question, SaveAnswer $save): JsonResponse
    {
        Gate::authorize('view', $attempt);
        $answer = $save($attempt, $question, $request->validated('option_id'));

        return response()->json(['data' => [
            'question_id' => $answer->assessment_question_id,
            'selected_option_id' => $answer->assessment_option_id,
            'answered' => $attempt->answers()->whereNotNull('assessment_option_id')->count(),
        ]]);
    }

    /**
     * Submit an attempt for scoring.
     *
     * Unanswered questions count as incorrect. Returns the result. 409 if already submitted.
     */
    public function submit(AssessmentAttempt $attempt, SubmitAttempt $submit, AttemptResult $result): JsonResponse
    {
        Gate::authorize('view', $attempt);

        return response()->json(['data' => $result->build($submit($attempt))]);
    }

    /**
     * The result of a submitted attempt.
     *
     * Overall and domain scores with bands and counts, the formula, strengths, gaps, next steps,
     * and a review that reveals the correct answer and explanation only for questions I got
     * wrong or left unanswered. 409 while the attempt is still open.
     */
    public function result(AssessmentAttempt $attempt, AttemptResult $result): JsonResponse
    {
        Gate::authorize('view', $attempt);
        if (! $attempt->isSubmitted()) {
            throw new ConflictHttpException(__('Submit this attempt to see its result.'));
        }

        return response()->json(['data' => $result->build($attempt)]);
    }

    public static function loadForTaking(AssessmentAttempt $attempt): AssessmentAttempt
    {
        return $attempt->load(['assessment', 'answers.question.domain', 'answers.question.options']);
    }
}

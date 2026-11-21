<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Modules\Learning\Actions\SubmitExercise;
use App\Modules\Learning\Http\Requests\SubmitExerciseRequest;
use App\Modules\Learning\Services\ExercisePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExerciseController extends Controller
{
    /**
     * A lesson's exercise.
     *
     * The questions without their answers, each with the lesson section that teaches it, the
     * pass mark, and whether I have passed. 404 when the lesson has no exercise.
     */
    public function show(Request $request, string $slug, ExercisePresenter $presenter): JsonResponse
    {
        [$lesson, $exercise] = $this->exercise($slug);

        return response()->json(['data' => $presenter->exercise($lesson, $exercise, $request->user())]);
    }

    /**
     * Submit an attempt.
     *
     * Scores every question (all or nothing; a question left out is wrong) and returns the result
     * with explanations, theory references and, for wrong answers, the correct answer. A passing
     * attempt completes the lesson.
     */
    public function submit(SubmitExerciseRequest $request, string $slug, SubmitExercise $submit, ExercisePresenter $presenter): JsonResponse
    {
        [$lesson, $exercise] = $this->exercise($slug);
        $attempt = $submit($request->user(), $lesson, $exercise, $request->validated('answers'), (int) $request->validated('time_spent_seconds', 0));

        return response()->json(['data' => $presenter->result($attempt)], 201);
    }

    /** One of my exercise attempts, with its result. Someone else's attempt is 404. */
    public function attempt(Request $request, int $id, ExercisePresenter $presenter): JsonResponse
    {
        $attempt = ExerciseAttempt::query()->whereKey($id)->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['data' => $presenter->result($attempt)]);
    }

    /** @return array{0: Lesson, 1: Exercise} */
    private function exercise(string $slug): array
    {
        $lesson = Lesson::query()->where('slug', $slug)->visibleToLearners()->with('chapter.course')->firstOrFail();
        $exercise = Exercise::query()->where('lesson_id', $lesson->id)->whereHas('questions')->firstOrFail();

        return [$lesson, $exercise];
    }
}

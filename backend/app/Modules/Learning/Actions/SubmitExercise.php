<?php

namespace App\Modules\Learning\Actions;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseQuestion;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Learning\Events\ExerciseSubmitted;
use App\Modules\Learning\Services\QuestionTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scores an exercise attempt (ARCHITECTURE D31) and, when it passes, completes the lesson in
 * the same transaction. Every current question gets an answer row; a question left out counts
 * as unanswered and wrong. Responses of the wrong shape are rejected before anything is written.
 */
final class SubmitExercise
{
    public function __construct(
        private readonly QuestionTypes $types,
        private readonly RecordLessonProgress $progress,
    ) {}

    /** @param  list<array{question_id: int, response?: mixed}>  $answers */
    public function __invoke(User $user, Lesson $lesson, Exercise $exercise, array $answers, int $secondsSpent): ExerciseAttempt
    {
        $questions = $exercise->questions()->get()->keyBy('id');
        $responses = [];
        $errors = [];
        foreach ($answers as $i => $answer) {
            /** @var ExerciseQuestion|null $question */
            $question = $questions[$answer['question_id']] ?? null;
            if (! $question) {
                $errors["answers.{$i}.question_id"] = [__('This question is not part of the exercise.')];
            } elseif (array_key_exists($question->id, $responses)) {
                $errors["answers.{$i}.question_id"] = [__('Each question can be answered once.')];
            } elseif ($message = $this->types->responseError($question->type, $question->payload, $answer['response'] ?? null)) {
                $errors["answers.{$i}.response"] = [$message];
            } else {
                $responses[$question->id] = $answer['response'] ?? null;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($user, $lesson, $exercise, $questions, $responses, $secondsSpent) {
            $graded = $questions->map(fn (ExerciseQuestion $q) => [
                'question' => $q,
                'response' => $responses[$q->id] ?? null,
                'correct' => $this->types->isCorrect($q->type, $q->answer, $responses[$q->id] ?? null),
            ]);
            $score = (int) round($graded->where('correct', true)->count() / max(1, $graded->count()) * 100);
            $seconds = min(max($secondsSpent, 0), RecordLessonProgress::MAX_SECONDS_PER_VISIT);

            $attempt = ExerciseAttempt::query()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'score_percent' => $score,
                'passed' => $score >= $exercise->pass_percent,
                'time_spent_seconds' => $seconds,
                'submitted_at' => now(),
            ]);
            $attempt->answers()->createMany($graded->map(fn (array $g) => [
                'exercise_question_id' => $g['question']->id,
                'response' => $g['response'],
                'is_correct' => $g['correct'],
            ])->values()->all());

            if ($attempt->passed) {
                $this->progress->complete($user, $lesson, $seconds);
            } else {
                $this->progress->start($user, $lesson);
            }

            ExerciseSubmitted::dispatch($attempt);

            return $attempt;
        });
    }
}

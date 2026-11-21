<?php

namespace App\Modules\Learning\Services;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseAttemptAnswer;
use App\Models\ExerciseQuestion;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

/**
 * The public shapes of an exercise (no answer key) and of a scored attempt (D31: every question
 * shows its explanation and theory reference; wrong ones also show the correct answer).
 */
final class ExercisePresenter
{
    /** @return array<string, mixed> */
    public function exercise(Lesson $lesson, Exercise $exercise, User $user): array
    {
        $exercise->loadMissing('questions.section:id,uuid,heading');
        $attempts = fn () => $user->exerciseAttempts()->where('exercise_id', $exercise->id);

        return [
            'lesson' => ['slug' => $lesson->slug, 'title' => $lesson->title],
            'pass_percent' => $exercise->pass_percent,
            'question_count' => $exercise->questions->count(),
            'questions' => $exercise->questions->map(fn (ExerciseQuestion $q) => $this->question($q))->all(),
            'passed' => $attempts()->where('passed', true)->exists(),
            'best_score' => $attempts()->max('score_percent'),
            'attempt_count' => $attempts()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function result(ExerciseAttempt $attempt): array
    {
        $attempt->loadMissing(['exercise.lesson:id,slug,title', 'answers.question.section:id,uuid,heading']);
        $answers = $attempt->answers->sortBy(fn (ExerciseAttemptAnswer $a) => $a->question->position)->values();
        $lesson = $attempt->exercise->lesson;
        $progress = LessonProgress::query()->where('user_id', $attempt->user_id)->where('lesson_id', $lesson->id)->first();

        return [
            'id' => $attempt->id,
            'lesson' => ['slug' => $lesson->slug, 'title' => $lesson->title],
            'score' => $attempt->score_percent,
            'passed' => $attempt->passed,
            'pass_percent' => $attempt->exercise->pass_percent,
            'correct' => $answers->where('is_correct', true)->count(),
            'total' => $answers->count(),
            'submitted_at' => $attempt->submitted_at->toIso8601String(),
            'lesson_status' => $progress?->status->value ?? 'not_started',
            'questions' => $answers->map(fn (ExerciseAttemptAnswer $a) => $this->question($a->question) + [
                'response' => $a->response,
                'is_correct' => $a->is_correct,
                'correct_answer' => $a->is_correct ? null : $a->question->answer,
                'explanation' => $a->question->explanation,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function question(ExerciseQuestion $q): array
    {
        return [
            'id' => $q->id,
            'position' => $q->position,
            'type' => $q->type->value,
            'prompt' => $q->prompt,
            'payload' => (object) $q->payload,
            'section' => $q->section ? ['id' => $q->section->uuid, 'heading' => $q->section->heading] : null,
        ];
    }
}

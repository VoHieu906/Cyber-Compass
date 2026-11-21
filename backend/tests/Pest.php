<?php

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Learning\Actions\RecordLessonProgress;
use App\Modules\Learning\Actions\SubmitExercise;
use App\Modules\Learning\Services\QuestionTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the full application against the MySQL testing database
| (cybercompass_testing), refreshed per test.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Requests come from the SPA origin, so Sanctum treats them as stateful (session cookie auth).
        $this->withHeaders([
            'Origin' => config('app.frontend_url'),
            'Referer' => config('app.frontend_url').'/',
        ]);
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Completes a lesson the way a learner does: passes its exercise when it has one (D31),
 * otherwise marks it read to the end.
 */
function passLesson(User $user, Lesson|string $lesson, int $seconds = 600): void
{
    $lesson = is_string($lesson) ? Lesson::query()->where('slug', $lesson)->firstOrFail() : $lesson;
    $lesson->loadMissing('chapter.course');
    $exercise = Exercise::query()->where('lesson_id', $lesson->id)->whereHas('questions')->first();
    if (! $exercise) {
        app(RecordLessonProgress::class)->complete($user, $lesson, $seconds);

        return;
    }
    $types = app(QuestionTypes::class);
    $answers = $exercise->questions()->get()->map(fn ($q) => ['question_id' => $q->id, 'response' => $types->correctResponse($q->type, $q->answer)])->all();
    app(SubmitExercise::class)($user, $lesson, $exercise, $answers, $seconds);
}

<?php

use App\Models\Exercise;
use App\Models\ExerciseQuestion;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Learning\Services\ExerciseLoader;
use Illuminate\Support\Str;

/*
| ExerciseLoader (ARCHITECTURE D29): the whole set is validated first, questions upsert by
| key, removed questions are archived, and every theory reference must exist in its lesson.
*/

/** A one-lesson exercise file for `hashing-and-passwords`, which has no exercise in the seed. */
function exerciseFile(array $questions = []): array
{
    return ['hashing-and-passwords' => ['pass_percent' => 60, 'questions' => $questions ?: [
        'salt' => ['type' => 'true_false', 'prompt' => 'A salt makes identical passwords hash differently.', 'answer' => true, 'explanation' => 'Each password gets its own salt.', 'section' => firstSectionKey()],
        'order' => ['type' => 'ordering', 'prompt' => 'Order these.', 'items' => ['a', 'b', 'c'], 'explanation' => 'In order.', 'section' => firstSectionKey()],
    ]]];
}

function firstSectionKey(): string
{
    $heading = Lesson::query()->where('slug', 'hashing-and-passwords')->firstOrFail()->sections()->whereNotNull('heading')->value('heading');

    return Str::slug($heading);
}

/** Loads the seeded files plus the test file, as the seeder does. */
function loadWith(array $extra): array
{
    $lessons = [];
    foreach (glob(database_path('seeders/content/exercises/*.php')) as $file) {
        $lessons += require $file;
    }

    return app(ExerciseLoader::class)->load($lessons + $extra);
}

it('loads every seeded exercise file without errors', function () {
    $report = loadWith([]);
    // One exercise per lesson in the files, so adding a course's exercises never edits this test.
    $files = collect(glob(database_path('seeders/content/exercises/*.php')))->sum(fn ($f) => count(require $f));

    expect($report['exercises'])->toBe($files)->toBeGreaterThan(20)
        ->and($report['questions'])->toBe(ExerciseQuestion::query()->where('is_archived', false)->count())
        ->and(ExerciseQuestion::query()->whereNull('lesson_section_id')->count())->toBe(0);
});

it('upserts by key: loading twice changes nothing', function () {
    loadWith(exerciseFile());
    $ids = ExerciseQuestion::query()->orderBy('id')->pluck('id')->all();
    loadWith(exerciseFile());

    expect(ExerciseQuestion::query()->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and(Exercise::query()->whereHas('lesson', fn ($q) => $q->where('slug', 'hashing-and-passwords'))->value('pass_percent'))->toBe(60);
});

it('archives a question that leaves the file, and every question of a lesson that leaves', function () {
    loadWith(exerciseFile());
    $file = exerciseFile();
    unset($file['hashing-and-passwords']['questions']['order']);

    $report = loadWith($file);
    expect($report['archived'])->toBe(['hashing-and-passwords/order'])
        ->and(ExerciseQuestion::query()->where('key', 'order')->sole()->is_archived)->toBeTrue();

    $report = loadWith([]);
    expect($report['archived'])->toBe(['hashing-and-passwords/salt']);
});

it('fails the whole load on a theory reference that does not exist, writing nothing', function () {
    $file = exerciseFile();
    $file['hashing-and-passwords']['questions']['salt']['section'] = 'no-such-section';
    $file['hashing-and-passwords']['questions']['order']['section_uuid'] = '00000000-0000-4000-8000-000000000000';
    unset($file['hashing-and-passwords']['questions']['order']['section']);
    $before = ExerciseQuestion::query()->count();

    expect(fn () => loadWith($file))->toThrow(InvalidArgumentException::class, 'hashing-and-passwords/salt: section "no-such-section" does not exist in this lesson');
    expect(ExerciseQuestion::query()->count())->toBe($before);
});

it('refuses a section of another lesson', function () {
    $file = exerciseFile();
    $file['hashing-and-passwords']['questions']['salt']['section_uuid'] = Lesson::query()->where('slug', 'cia-triad')->firstOrFail()->sections()->value('uuid');

    expect(fn () => loadWith($file))->toThrow(InvalidArgumentException::class, 'does not exist in this lesson');
});

it('lists every problem at once', function () {
    $message = '';
    try {
        loadWith([
            'no-such-lesson' => ['questions' => ['q' => []]],
            'hashing-and-passwords' => ['pass_percent' => 0, 'questions' => ['Bad Key' => ['type' => 'true_false', 'answer' => 'yes']]],
        ]);
    } catch (InvalidArgumentException $e) {
        $message = $e->getMessage();
    }

    expect($message)->toContain('no-such-lesson: no lesson with this slug')
        ->toContain('pass_percent must be 1 to 100')
        ->toContain('the key must be lowercase words')
        ->toContain('prompt is required')
        ->toContain('answer must be true or false')
        ->toContain('section (a section key) or section_uuid is required');
});

it('refuses to change the type of a question someone has answered', function () {
    loadWith(exerciseFile());
    passLesson(User::factory()->create(), 'hashing-and-passwords');
    $file = exerciseFile();
    $file['hashing-and-passwords']['questions']['salt'] = ['type' => 'single_choice', 'prompt' => 'Now a choice.', 'options' => ['Yes', 'No'], 'answer' => 0, 'explanation' => 'Yes.', 'section' => firstSectionKey()];

    expect(fn () => loadWith($file))->toThrow(InvalidArgumentException::class, 'its type cannot change');
});

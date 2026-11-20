<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonDraft;
use App\Models\LessonSection;
use App\Models\User;
use App\Modules\Content\Services\ContentExporter;
use App\Modules\Content\Services\ContentImporter;
use App\Modules\Content\Services\PhpArrayWriter;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/*
| content:export writes live content back to the content-file format (PHASE_3_5 M4). The
| property that matters: importing an export changes nothing, section UUIDs included.
*/

/** Everything an import writes for a course, keyed by lesson slug. */
function courseSnapshot(string $slug): array
{
    return Lesson::query()
        ->whereHas('chapter.course', fn ($q) => $q->where('slug', $slug))
        ->with(['chapter', 'sections'])
        ->orderBy('slug')
        ->get()
        ->mapWithKeys(fn (Lesson $l) => [$l->slug => [
            'chapter' => [$l->chapter->position, $l->chapter->title],
            'position' => $l->position,
            'status' => $l->status->value,
            'fields' => [$l->title, $l->summary, $l->estimated_minutes, $l->objectives, $l->key_takeaways, $l->resources],
            'sections' => $l->sections->map(fn (LessonSection $s) => [$s->uuid, $s->type, $s->heading, $s->payload])->all(),
        ]])
        ->all();
}

/** Evaluates exported PHP the way the seeder does: by requiring the file. */
function evalExport(string $php): array
{
    $file = tempnam(sys_get_temp_dir(), 'export').'.php';
    file_put_contents($file, $php);
    try {
        return require $file;
    } finally {
        @unlink($file);
    }
}

$export = fn (string $slug) => app(ContentExporter::class)->export(Course::query()->where('slug', $slug)->firstOrFail());
$import = fn (array $course) => app(ContentImporter::class)->import([$course], force: true);

it('round-trips every seeded course without changing anything', function () use ($export, $import) {
    foreach (glob(database_path('seeders/content/courses/*.php')) as $file) {
        $slug = basename($file, '.php');
        $before = courseSnapshot($slug);

        $result = $export($slug);
        $import(evalExport($result['php']));

        expect(courseSnapshot($slug))->toEqual($before, "course {$slug}")
            ->and($result['lessons'])->toBe(count($before));
    }
});

it('pins the UUID of a section made in the admin console, so re-importing keeps it', function () use ($export, $import) {
    $lesson = Lesson::query()->where('slug', 'subnetting')->firstOrFail();
    $uuid = Uuid::uuid4()->toString();
    LessonSection::query()->create([
        'uuid' => $uuid, 'lesson_id' => $lesson->id, 'position' => 99,
        'type' => 'callout', 'heading' => 'Added in the admin', 'payload' => ['text' => 'New.', 'tone' => 'tip'],
    ]);

    $php = $export('networking-fundamentals')['php'];
    expect($php)->toContain("'uuid' => '{$uuid}'");

    $import(evalExport($php));
    expect(LessonSection::query()->where('uuid', $uuid)->value('lesson_id'))->toBe($lesson->id)
        // Seeded sections keep their derived UUIDs and are written without one.
        ->and(substr_count($php, "'uuid' =>"))->toBe(1);
});

it('exports the live lesson, not an unpublished draft', function () use ($export) {
    $lesson = Lesson::query()->where('slug', 'subnetting')->firstOrFail();
    LessonDraft::query()->create([
        'lesson_id' => $lesson->id, 'lock_version' => 1, 'updated_by' => User::factory()->create()->id,
        'snapshot' => ['title' => 'Draft title that must not leak'],
    ]);

    $result = $export('networking-fundamentals');

    expect($result['skipped_drafts'])->toBe(['subnetting'])
        ->and($result['php'])->not->toContain('Draft title that must not leak');
});

it('refuses a pinned UUID that belongs to another lesson', function () use ($import) {
    $course = require database_path('seeders/content/courses/networking-fundamentals.php');
    $other = LessonSection::query()->whereHas('lesson', fn ($q) => $q->where('slug', 'cia-triad'))->value('uuid');
    $course['chapters'][0]['lessons'][0]['sections'][0]['uuid'] = $other;

    $import($course);
})->throws(ValidationException::class, 'belongs to lesson cia-triad');

it('writes strings back exactly, whatever they contain', function () {
    $tricky = [
        'quotes' => "It's a \"test\" with \\ backslashes and \$dollars",
        'multi' => "Line one\n\n- bullet with `code`\nTXT\n  indented\n",
        'crlf' => "Windows\r\nline",
        'unicode' => 'Mật khẩu → ✓',
        'empty' => '',
        'numbers' => [1, 2.5, -3.0, 0.1],
        'flags' => [true, false, null],
        'nested' => ['a' => ['b' => ['c' => str_repeat('long ', 40)]]],
    ];

    expect(evalExport("<?php\n\nreturn ".PhpArrayWriter::write($tricky).";\n"))->toBe($tricky);
});

it('writes the file through the command, or prints it', function () {
    $path = storage_path('app/export-test.php');

    $this->artisan('content:export', ['course' => 'security-fundamentals', '--path' => $path])->assertSuccessful();
    expect((require $path)['slug'])->toBe('security-fundamentals');
    @unlink($path);

    $this->artisan('content:export', ['course' => 'security-fundamentals', '--stdout' => true])
        ->expectsOutputToContain("'slug' => 'security-fundamentals'")
        ->assertSuccessful();

    $this->artisan('content:export', ['course' => 'nope'])->assertFailed();
});

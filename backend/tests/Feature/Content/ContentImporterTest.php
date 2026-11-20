<?php

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\LessonDraft;
use App\Models\LessonSection;
use App\Models\User;
use App\Modules\Content\Services\ContentImporter;
use Illuminate\Validation\ValidationException;

/*
| The importer is how content files reach the database (ARCHITECTURE D19).
| Tests run on the seeded content, then re-import a modified copy of one course file.
*/

$course = fn () => require database_path('seeders/content/courses/applied-cryptography.php');
$import = fn (array $course, bool $force = false) => app(ContentImporter::class)->import([$course], $force);
$uuids = fn (string $slug) => LessonSection::query()
    ->whereHas('lesson', fn ($q) => $q->where('slug', $slug))->orderBy('position')->pluck('uuid')->all();

it('keeps section ids stable across re-imports and section reordering', function () use ($course, $import, $uuids) {
    $before = $uuids('hashing-and-passwords');

    $data = $course();
    $import($data);
    expect($uuids('hashing-and-passwords'))->toBe($before);

    $sections = &$data['chapters'][0]['lessons'][0]['sections'];
    $sections = array_reverse($sections);
    $import($data);

    expect($uuids('hashing-and-passwords'))->toBe(array_reverse($before));
});

it('exposes the stable section id to learners', function () use ($uuids) {
    $user = User::factory()->create();

    $ids = collect($this->actingAs($user, 'web')->getJson('/api/v1/lessons/hashing-and-passwords')->json('data.sections'))->pluck('id')->all();

    expect($ids)->toBe($uuids('hashing-and-passwords'))
        ->and($ids[0])->toMatch('/^[0-9a-f-]{36}$/');
});

it('reorders lessons without tripping the unique position index', function () use ($course, $import) {
    $data = $course();
    $data['chapters'][0]['lessons'] = array_reverse($data['chapters'][0]['lessons']);

    $import($data);

    expect(Lesson::query()->where('slug', 'symmetric-asymmetric-encryption')->value('position'))->toBe(1)
        ->and(Lesson::query()->where('slug', 'hashing-and-passwords')->value('position'))->toBe(2);
});

it('archives a lesson removed from the file instead of leaving it live', function () use ($course, $import) {
    $data = $course();
    array_pop($data['chapters'][1]['lessons']); // tls-overview

    $report = $import($data);

    expect($report['archived'])->toBe(['tls-overview'])
        ->and(Lesson::query()->where('slug', 'tls-overview')->value('status'))->toBe(LessonStatus::Archived);
});

it('skips lessons edited in the admin unless forced', function () use ($course, $import) {
    $admin = User::factory()->admin()->create();
    Lesson::query()->where('slug', 'tls-overview')->update(['title' => 'Edited by an admin', 'updated_by' => $admin->id]);

    $report = $import($course());
    expect($report['skipped'])->toBe(['tls-overview'])
        ->and(Lesson::query()->where('slug', 'tls-overview')->value('title'))->toBe('Edited by an admin');

    $import($course(), force: true);
    expect(Lesson::query()->where('slug', 'tls-overview')->value('title'))->toBe('How TLS protects a connection');
});

it('also treats a lesson with a pending draft as admin-owned', function () use ($course, $import) {
    $lesson = Lesson::query()->where('slug', 'tls-overview')->firstOrFail();
    LessonDraft::query()->create(['lesson_id' => $lesson->id, 'snapshot' => ['title' => 'x']]);

    expect($import($course())['skipped'])->toBe(['tls-overview']);
});

it('rejects invalid content with the same rules as the admin and writes nothing', function () use ($course, $import) {
    $data = $course();
    $data['title'] = 'Should not be written';
    $data['chapters'][0]['lessons'][0]['sections'][] = ['callout', 'Bad', 'Text', 'loud'];

    try {
        $import($data);
        $this->fail('expected a validation error');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('lessons.hashing-and-passwords.sections.4.payload.tone');
    }

    $this->assertDatabaseMissing('courses', ['title' => 'Should not be written']);
});

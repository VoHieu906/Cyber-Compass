<?php

use App\Enums\LessonStatus;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\Lesson;
use App\Models\LessonArchitecture;
use App\Models\LessonRevision;
use App\Models\User;
use App\Modules\Content\Services\ContentExporter;
use App\Modules\Content\Services\ContentImporter;
use App\Modules\Content\Services\LessonWorkingCopy;
use Illuminate\Validation\ValidationException;

/*
| The course map (F3, D57, D58): content files, what readers see, integrity when lessons
| change, the admin panel, the lesson snapshot and the health check. Built on a copy of the
| applied-cryptography course file with a small test map; no seeded course has one yet.
*/

const MAP_COURSE = 'applied-cryptography';

function mapCourseFile(): array
{
    $course = require database_path('seeders/content/courses/'.MAP_COURSE.'.php');
    $course['graph'] = [
        'version' => 1,
        'problem' => 'Protect data in storage and in transit from people who should not read or change it.',
        'layout' => 'manual',
        'placement' => [
            'hashing-and-passwords' => ['col' => 0, 'row' => 0],
            'symmetric-asymmetric-encryption' => ['col' => 1, 'row' => 0],
            'tls-overview' => ['col' => 2, 'row' => 0],
        ],
        'edges' => [
            ['from' => 'hashing-and-passwords', 'to' => 'symmetric-asymmetric-encryption', 'type' => 'contrasts-with', 'label' => 'one-way vs keyed'],
            ['from' => 'symmetric-asymmetric-encryption', 'to' => 'tls-overview', 'type' => 'feeds-into'],
        ],
    ];
    $course['chapters'][0]['lessons'][0]['architecture'] = [
        'version' => 1,
        'problem' => 'Store passwords so a stolen database does not reveal them.',
        'shape' => 'pipeline',
        'nodes' => [
            ['id' => 'input', 'label' => 'Password', 'section' => 'what-a-hash-does'],
            ['id' => 'slow', 'label' => 'Slow hash', 'section' => 'passwords-need-slow-hashes', 'note' => 'bcrypt, scrypt, Argon2.'],
            ['id' => 'store', 'label' => 'Stored digest', 'lesson' => 'symmetric-asymmetric-encryption'],
        ],
        'edges' => [['from' => 'input', 'to' => 'slow']],
    ];
    $course['chapters'][0]['lessons'][1]['architecture'] = [
        'version' => 1, 'problem' => 'Share secrets with people you have never met.', 'shape' => null, 'nodes' => [], 'edges' => [],
    ];

    return $course;
}

function importMap(array $course, bool $force = false): array
{
    return app(ContentImporter::class)->import([$course], $force);
}

function learnerMap(): ?array
{
    return test()->getJson('/api/v1/courses/'.MAP_COURSE)->assertOk()->json('data.graph');
}

it('imports the map, resolves section keys and serves it on the course page', function () {
    importMap(mapCourseFile());

    $map = learnerMap();
    $hashing = collect($map['lessons'])->firstWhere('slug', 'hashing-and-passwords');

    expect($map['problem'])->toStartWith('Protect data')
        ->and(collect($map['lessons'])->pluck('slug')->all())->toBe(['hashing-and-passwords', 'symmetric-asymmetric-encryption', 'tls-overview'])
        ->and($map['edges'])->toHaveCount(2)
        ->and($hashing['shape'])->toBe('pipeline')
        ->and($hashing['placement'])->toBe(['col' => 0, 'row' => 0])
        ->and($hashing['nodes'][0]['section'])->toBe(ContentImporter::sectionUuid('hashing-and-passwords', 'what-a-hash-does'))
        ->and($hashing['nodes'][2]['lesson'])->toBe('symmetric-asymmetric-encryption')
        ->and(collect($map['lessons'])->firstWhere('slug', 'tls-overview')['problem'])->toBeNull();

    // Every sub-node section is a real anchor in the reader.
    $ids = collect($this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/lessons/hashing-and-passwords')->json('data.sections'))->pluck('id');
    expect($ids)->toContain($hashing['nodes'][1]['section']);
});

it('shows nothing for a course without a map', function () {
    expect($this->getJson('/api/v1/courses/linux-command-line')->json('data'))->toHaveKey('graph', null);
});

it('drops a hidden lesson with its edges, placement and links, on the server', function (LessonStatus $status) {
    importMap(mapCourseFile());
    Lesson::query()->where('slug', 'symmetric-asymmetric-encryption')->update(['status' => $status]);

    $map = learnerMap();
    $json = json_encode($map);

    expect(collect($map['lessons'])->pluck('slug')->all())->toBe(['hashing-and-passwords', 'tls-overview'])
        ->and($map['edges'])->toBe([])
        ->and($json)->not->toContain('symmetric-asymmetric-encryption');
})->with([LessonStatus::Archived, LessonStatus::Draft]);

it('places a new lesson automatically and reports it as unplaced and unconnected', function () {
    $file = mapCourseFile();
    expect(importMap($file)['warnings'])->toBe([MAP_COURSE.': no_problem tls-overview']);

    $admin = User::factory()->admin()->create();
    $id = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/content/lessons', [
        'chapter_id' => Course::query()->where('slug', MAP_COURSE)->first()->chapters()->first()->id,
        'title' => 'Key exchange', 'slug' => 'key-exchange',
    ])->json('data.id');
    $lesson = Lesson::query()->findOrFail($id);
    $lesson->update(['status' => LessonStatus::Published, 'published_at' => now()]);

    expect(collect(learnerMap()['lessons'])->firstWhere('slug', 'key-exchange'))->toMatchArray(['placement' => null, 'problem' => null]);

    $issues = $this->actingAs($admin, 'web')->getJson('/api/v1/admin/content/courses/'.$lesson->chapter->course_id.'/graph')->json('data.issues');
    expect($issues)->toContain(['type' => 'unplaced', 'lesson' => 'key-exchange'])
        ->toContain(['type' => 'unconnected', 'lesson' => 'key-exchange'])
        ->toContain(['type' => 'no_problem', 'lesson' => 'key-exchange']);
});

it('warns on import about unplaced and unconnected lessons without refusing', function () {
    $file = mapCourseFile();
    unset($file['graph']['placement']['tls-overview']);
    array_pop($file['graph']['edges']);

    expect(importMap($file)['warnings'])->toBe([
        MAP_COURSE.': unplaced tls-overview',
        MAP_COURSE.': unconnected tls-overview',
        MAP_COURSE.': no_problem tls-overview',
    ]);
});

it('refuses a content file whose map names what is not there, writing nothing', function (Closure $break, string $key) {
    $file = mapCourseFile();
    $break($file);

    expect(fn () => importMap($file))->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($key));
    expect(CourseGraph::query()->count())->toBe(0)->and(LessonArchitecture::query()->count())->toBe(0);
})->with([
    'an edge to a lesson of another course' => [fn (&$f) => $f['graph']['edges'][0]['to'] = 'cia-triad', 'courses.'.MAP_COURSE.'.graph.edges.0.to'],
    'an unknown section key' => [fn (&$f) => $f['chapters'][0]['lessons'][0]['architecture']['nodes'][0]['section'] = 'no-such-section', 'lessons.hashing-and-passwords.architecture.nodes.0.section'],
    'a link out of the course' => [fn (&$f) => $f['chapters'][0]['lessons'][0]['architecture']['nodes'][2]['lesson'] = 'cia-triad', 'lessons.hashing-and-passwords.architecture.nodes.2.lesson'],
    'a shape that does not fit' => [fn (&$f) => $f['chapters'][0]['lessons'][0]['architecture']['shape'] = 'matrix', 'lessons.hashing-and-passwords.architecture.rows'],
]);

it('exports the map and re-imports it unchanged, section keys included', function () {
    importMap(mapCourseFile());
    $before = [CourseGraph::query()->first()->graph, LessonArchitecture::query()->orderBy('lesson_id')->pluck('architecture')->all()];

    $export = app(ContentExporter::class)->export(Course::query()->where('slug', MAP_COURSE)->firstOrFail());
    $file = tempnam(sys_get_temp_dir(), 'map').'.php';
    file_put_contents($file, $export['php']);
    $data = require $file;
    unlink($file);

    expect($data['graph'])->toEqual($before[0])
        ->and($data['chapters'][0]['lessons'][0]['architecture']['nodes'][0]['section'])->toBe('what-a-hash-does');

    importMap($data);
    expect([CourseGraph::query()->first()->graph, LessonArchitecture::query()->orderBy('lesson_id')->pluck('architecture')->all()])->toEqual($before);
});

it('removes a map or architecture that left the file', function () {
    importMap(mapCourseFile());
    $file = require database_path('seeders/content/courses/'.MAP_COURSE.'.php');
    importMap($file);

    expect(CourseGraph::query()->count())->toBe(0)->and(LessonArchitecture::query()->count())->toBe(0);
});

describe('admin panel', function () {
    beforeEach(function () {
        importMap(mapCourseFile());
        $this->admin = User::factory()->admin()->create();
        $this->course = Course::query()->where('slug', MAP_COURSE)->firstOrFail();
        $this->url = "/api/v1/admin/content/courses/{$this->course->id}/graph";
    });

    it('shows the map with lessons, issues and history', function () {
        $data = $this->actingAs($this->admin, 'web')->getJson($this->url)->assertOk()->json('data');

        expect($data['lock_version'])->toBe(1)
            ->and($data['owned_by_admin'])->toBeFalse()
            ->and(collect($data['lessons'])->firstWhere('slug', 'hashing-and-passwords')['shape'])->toBe('pipeline')
            ->and($data['issues'])->toBe([['type' => 'no_problem', 'lesson' => 'tls-overview']])
            ->and($data['history'])->toBe([]);
    });

    it('saves live, audits, refuses a stale copy and makes imports skip the map', function () {
        $graph = CourseGraph::query()->first()->graph;
        $graph['edges'][] = ['from' => 'hashing-and-passwords', 'to' => 'gone-lesson', 'type' => 'requires'];

        $data = $this->actingAs($this->admin, 'web')->putJson($this->url, ['graph' => $graph, 'lock_version' => 1])->assertOk()->json('data');
        expect($data['lock_version'])->toBe(2)
            ->and($data['owned_by_admin'])->toBeTrue()
            // A lesson that is not there never blocks a save; it is listed instead.
            ->and($data['issues'])->toContain(['type' => 'dangling', 'lesson' => 'gone-lesson', 'detail' => 'edge'])
            ->and(learnerMap()['edges'])->toHaveCount(2);

        $entry = AdminAuditLog::query()->where('action', 'course_graph.saved')->firstOrFail();
        expect($entry->subject_id)->toBe($this->course->id)->and($entry->changes['graph'][1]['edges'])->toHaveCount(3);

        $this->actingAs($this->admin, 'web')->putJson($this->url, ['graph' => $graph, 'lock_version' => 1])->assertStatus(409);

        $report = importMap(mapCourseFile());
        expect($report['graphs_skipped'])->toBe([MAP_COURSE])->and(CourseGraph::query()->first()->graph['edges'])->toHaveCount(3);
        importMap(mapCourseFile(), force: true);
        expect(CourseGraph::query()->first()->graph['edges'])->toHaveCount(2);
    });

    it('rejects an invalid map at its field', function () {
        $this->actingAs($this->admin, 'web')->putJson($this->url, ['graph' => ['version' => 1, 'problem' => '', 'layout' => 'auto', 'edges' => []], 'lock_version' => 1])
            ->assertJsonValidationErrors('graph.problem');
        $this->actingAs($this->admin, 'web')->putJson($this->url, ['graph' => CourseGraph::query()->first()->graph])
            ->assertJsonValidationErrors('lock_version');
    });

    it('restores the map before or after an audited change', function () {
        $seeded = CourseGraph::query()->first()->graph;
        $changed = [...$seeded, 'problem' => 'A different big problem.'];
        $this->actingAs($this->admin, 'web')->putJson($this->url, ['graph' => $changed, 'lock_version' => 1])->assertOk();
        $entry = AdminAuditLog::query()->where('action', 'course_graph.saved')->firstOrFail();

        $this->actingAs($this->admin, 'web')->postJson("{$this->url}/restore/{$entry->id}", ['lock_version' => 2, 'side' => 'before'])
            ->assertOk()->assertJsonPath('data.graph.problem', $seeded['problem'])->assertJsonPath('data.history.0.action', 'course_graph.restored');
        $this->actingAs($this->admin, 'web')->postJson("{$this->url}/restore/{$entry->id}", ['lock_version' => 3])
            ->assertOk()->assertJsonPath('data.graph.problem', 'A different big problem.');

        $other = AdminAuditLog::query()->create(['action' => 'chapter.renamed', 'subject_type' => 'chapter', 'subject_id' => 1]);
        $this->actingAs($this->admin, 'web')->postJson("{$this->url}/restore/{$other->id}", ['lock_version' => 4])->assertJsonValidationErrors('entry');
    });

    it('is for admins only', function () {
        $this->getJson($this->url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'web')->putJson($this->url, ['graph' => [], 'lock_version' => 1])->assertForbidden();
    });
});

describe('lesson snapshot', function () {
    beforeEach(function () {
        importMap(mapCourseFile());
        $this->admin = User::factory()->admin()->create();
        $this->lesson = Lesson::query()->where('slug', 'hashing-and-passwords')->firstOrFail();
        $this->url = "/api/v1/admin/content/lessons/{$this->lesson->id}";
    });

    it('drafts, publishes and keeps the architecture in revisions', function () {
        $copy = $this->actingAs($this->admin, 'web')->getJson($this->url)->assertOk()->json('data.working_copy');
        expect($copy['architecture']['shape'])->toBe('pipeline');

        $copy['architecture']['problem'] = 'Keep passwords safe after a breach.';
        $this->actingAs($this->admin, 'web')->putJson("{$this->url}/draft", [...$copy, 'lock_version' => 0])->assertOk();
        // Readers still see the published architecture until the draft is published.
        expect(collect(learnerMap()['lessons'])->first()['problem'])->toStartWith('Store passwords');

        $this->actingAs($this->admin, 'web')->postJson("{$this->url}/publish", ['lock_version' => 1])->assertOk();
        expect(collect(learnerMap()['lessons'])->first()['problem'])->toBe('Keep passwords safe after a breach.')
            ->and(LessonRevision::query()->where('lesson_id', $this->lesson->id)->latest('version')->first()->snapshot['architecture']['problem'])->toBe('Keep passwords safe after a breach.');
    });

    it('refuses to drop a section a sub-node points at until the reference is cleared', function () {
        $copy = $this->actingAs($this->admin, 'web')->getJson($this->url)->json('data.working_copy');
        $referenced = $copy['architecture']['nodes'][0]['section'];
        $copy['sections'] = array_values(array_filter($copy['sections'], fn ($s) => $s['id'] !== $referenced));

        $this->actingAs($this->admin, 'web')->putJson("{$this->url}/draft", [...$copy, 'lock_version' => 0])
            ->assertJsonValidationErrors('architecture.nodes.0.section');

        unset($copy['architecture']['nodes'][0]['section']);
        $this->actingAs($this->admin, 'web')->putJson("{$this->url}/draft", [...$copy, 'lock_version' => 0])->assertOk();
    });

    it('drops links to another course when a lesson moves, and keeps its architecture', function () {
        $copy = $this->actingAs($this->admin, 'web')->getJson($this->url)->json('data.working_copy');
        $copy['chapter_id'] = Course::query()->where('slug', 'linux-command-line')->first()->chapters()->first()->id;

        $this->actingAs($this->admin, 'web')->putJson("{$this->url}/draft", [...$copy, 'lock_version' => 0])->assertOk();
        $this->actingAs($this->admin, 'web')->postJson("{$this->url}/publish", ['lock_version' => 1])->assertOk();

        $arch = LessonArchitecture::query()->where('lesson_id', $this->lesson->id)->first()->architecture;
        expect($arch['shape'])->toBe('pipeline')->and(array_column($arch['nodes'], 'lesson'))->toBe([])
            // Its edges in the old course's map are dropped for readers and listed for the admin.
            ->and(collect(learnerMap()['lessons'])->pluck('slug'))->not->toContain('hashing-and-passwords')
            ->and(learnerMap()['edges'])->toHaveCount(1);
    });

    it('lists sub-node links that readers will not see', function () {
        Lesson::query()->where('slug', 'symmetric-asymmetric-encryption')->update(['status' => LessonStatus::Archived]);

        $this->actingAs($this->admin, 'web')->getJson($this->url)->assertJsonPath('data.architecture_issues', ['symmetric-asymmetric-encryption']);
    });

    it('restores an older revision without an architecture as none', function () {
        LessonRevision::query()->create(['lesson_id' => $this->lesson->id, 'version' => 1, 'snapshot' => collect(app(LessonWorkingCopy::class)->fromLive($this->lesson))->except('architecture')->all()]);

        $this->actingAs($this->admin, 'web')->postJson("{$this->url}/revisions/1/restore", ['lock_version' => 0])
            ->assertOk()->assertJsonPath('data.working_copy.architecture', null);
    });
});

describe('content:check-graphs', function () {
    it('passes on the seed content', function () {
        $this->artisan('content:check-graphs')->assertSuccessful();
    });

    it('fails on a broken stored map and only warns in the release mode', function () {
        importMap(mapCourseFile());
        CourseGraph::query()->first()->update(['graph' => ['version' => 1, 'problem' => '', 'layout' => 'auto', 'edges' => []]]);

        $this->artisan('content:check-graphs')->assertFailed();
        $this->artisan('content:check-graphs', ['--warn-only' => true])->assertSuccessful();
    });

    it('reports unplaced lessons as warnings', function () {
        $file = mapCourseFile();
        unset($file['graph']['placement']['tls-overview']);
        importMap($file);

        $this->artisan('content:check-graphs')->expectsOutputToContain('unplaced tls-overview')->assertSuccessful();
    });
});

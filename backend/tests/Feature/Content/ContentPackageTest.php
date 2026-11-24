<?php

use App\Enums\LessonStatus;
use App\Models\AdminAuditLog;
use App\Models\ExerciseQuestion;
use App\Models\Lesson;
use App\Models\LessonArchitecture;
use App\Models\LessonMedia;
use App\Models\LessonSection;
use App\Models\Post;
use App\Models\User;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Packages\ImportPackage;
use App\Modules\Content\Packages\PackageExporter;
use App\Modules\Content\Packages\PackagePlan;
use App\Modules\Content\Packages\PackagePlanner;
use App\Modules\Content\Packages\PackageReader;
use App\Modules\Content\Services\ContentImporter;
use App\Modules\Learning\Services\ExerciseReferences;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/*
| Content packages (F6, D67): validated like a draft save, written only into drafts, never
| published, and never aware of any block type. Packages are passed through JSON as a file would
| be, so objects and lists arrive the way a real upload decodes them.
*/

const PKG_FIXTURES = __DIR__.'/../../../app/Modules/Content/Blocks/fixtures';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Storage::fake(config('cybercompass.uploads_disk'));
});

function envelope(string $kind, array $body, array $target = []): array
{
    return json_decode(json_encode([
        'format' => 'cybercompass.content', 'format_version' => 1, 'kind' => $kind,
        'generator' => 'Pest', 'target' => (object) $target, ...$body,
    ]), true);
}

function planOf(array $envelope, ?string $root = null): PackagePlan
{
    return app(PackagePlanner::class)->plan($envelope, $root);
}

function importPkg(array $envelope, ?string $root = null, ?array $locks = null): PackagePlan
{
    return app(ImportPackage::class)->handle(test()->admin, $envelope, $root, $locks);
}

/** The export a file would carry: JSON-encoded and decoded again. */
function exported(Lesson|Post $doc, ?array $keys = null): array
{
    $exporter = app(PackageExporter::class);

    return json_decode(PackageExporter::json($doc instanceof Lesson ? $exporter->lesson($doc->fresh(), $keys) : $exporter->post($doc->fresh(), $keys)), true);
}

function newLessonPackage(string $slug, array $sections, array $extra = []): array
{
    return envelope('lesson', ['document' => [
        'slug' => $slug, 'title' => 'Package lesson', 'minutes' => 12, 'summary' => 'From a package.',
        'objectives' => ['Read a package'], 'takeaways' => [], 'resources' => [], 'sections' => $sections, ...$extra,
    ]], ['course' => 'networking-fundamentals', 'chapter' => 'How networks are built']);
}

function osi(): Lesson
{
    return Lesson::query()->where('slug', 'osi-model')->firstOrFail();
}

function draftSections(Lesson|Post $doc): array
{
    return $doc->fresh()->draft->snapshot['sections'];
}

function makeZip(array $entries): string
{
    $file = tempnam(sys_get_temp_dir(), 'pkg').'.ccpkg.zip';
    $zip = new ZipArchive;
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $bytes) {
        $zip->addFromString($name, $bytes);
    }
    $zip->close();

    return $file;
}

function pngBytes(): string
{
    $image = imagecreatetruecolor(40, 30);
    imagefilledrectangle($image, 0, 0, 39, 29, imagecolorallocate($image, 30, 120, 200));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function readerErrors(callable $read): array
{
    try {
        $read();
    } catch (ValidationException $e) {
        return $e->errors();
    }
    test()->fail('expected the reader to refuse the package');
}

describe('every block type (the registry contract)', function () {
    $valid = collect(glob(PKG_FIXTURES.'/*.valid.json'))->flatMap(fn ($file) => collect(json_decode(file_get_contents($file), true))
        ->mapWithKeys(fn ($payload, $i) => [explode('.', basename($file))[0]." #{$i}" => [explode('.', basename($file))[0], $i, $payload]]))->all();
    $invalid = collect(glob(PKG_FIXTURES.'/*.invalid.json'))->flatMap(fn ($file) => collect(json_decode(file_get_contents($file), true))
        ->mapWithKeys(fn ($payload, $i) => [explode('.', basename($file))[0]." #{$i}" => [explode('.', basename($file))[0], $payload]]))->all();

    it('covers every registered type with fixtures', function () use ($valid) {
        expect(collect($valid)->pluck(0)->unique()->sort()->values()->all())
            ->toBe(collect(array_keys(BlockRegistry::default()->all()))->sort()->values()->all());
    });

    it('exports, imports and exports again without changing anything', function (string $type, int $i, array $payload) {
        LessonMedia::query()->firstOrCreate(['uuid' => '3f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30'], ['path' => 'x.webp', 'alt' => 'Fixture', 'width' => 4, 'height' => 3, 'size_bytes' => 1]);
        $slug = "pkg-{$type}-{$i}";
        importPkg(newLessonPackage($slug, [['key' => 'block', 'type' => $type, 'heading' => 'A block', 'payload' => $payload]]));
        $lesson = Lesson::query()->where('slug', $slug)->firstOrFail();

        $first = exported($lesson);
        expect($first['document']['sections'][0]['payload'])
            ->toEqual(BlockRegistry::default()->validateSections([['type' => $type, 'payload' => $payload]])[0]['payload']);

        importPkg($first);
        $second = exported($lesson);
        unset($first['base'], $second['base']);
        expect($second)->toEqual($first)
            ->and(draftSections($lesson)[0]['uuid'])->toBe(ContentImporter::sectionUuid($slug, 'block'));
    })->with($valid);

    it('rejects every invalid fixture with an error at the block', function (string $type, mixed $payload) {
        $plan = planOf(newLessonPackage('pkg-invalid', [['key' => 'block', 'type' => $type, 'payload' => $payload]]));

        expect($plan->valid())->toBeFalse();
        foreach ($plan->errors as $path => $messages) {
            expect($path)->toStartWith('document.sections.0')
                ->and($messages)->each->toBeString();
        }
        expect(Lesson::query()->where('slug', 'pkg-invalid')->exists())->toBeFalse();
    })->with($invalid);
});

describe('the envelope', function () {
    it('names what is wrong and what is supported', function () {
        $plan = planOf(['format' => 'other', 'format_version' => 2, 'kind' => 'chapter']);

        expect($plan->errors['format'][0])->toContain('cybercompass.content')
            ->and($plan->errors['format_version'][0])->toContain('version 2')
            ->and($plan->errors['kind'][0])->toContain('lesson, lesson-sections, post, post-sections, course');
    });

    it('lists the registered block types for an unknown one', function () {
        $plan = planOf(newLessonPackage('pkg-unknown', [['key' => 'x', 'type' => 'marquee', 'payload' => []]]));

        expect($plan->errors['document.sections.0.type'][0])->toContain('prose')->toContain('knowledge-check');
    });

    it('accepts a course package only as a zip', function () {
        expect(planOf(envelope('course', ['documents' => []], ['course' => 'networking-fundamentals']))->errors['kind'][0])->toContain('.ccpkg.zip');
    });
});

describe('operations on an existing lesson', function () {
    it('appends, inserts, replaces and removes, keeping the UUIDs of the sections it keeps', function () {
        $before = osi()->sections()->orderBy('position')->get();
        $prose = ['type' => 'prose', 'payload' => ['text' => 'New text.'], 'sources' => [['label' => 'RFC 1122', 'url' => 'https://www.rfc-editor.org/rfc/rfc1122', 'kind' => 'rfc']]];

        $plan = importPkg(envelope('lesson-sections', ['operations' => [
            ['op' => 'insert_after', 'key' => 'the-seven-layers', 'section' => ['key' => 'devices-by-layer', 'heading' => 'Devices by layer', ...$prose]],
            ['op' => 'insert_before', 'uuid' => $before[0]->uuid, 'section' => ['key' => 'opening', ...$prose]],
            ['op' => 'replace', 'key' => 'why-a-layered-model', 'uuid' => $before[0]->uuid, 'section' => ['heading' => 'Why layers', ...$prose]],
            ['op' => 'append', 'section' => ['heading' => 'In other words', 'type' => 'callout', 'payload' => ['text' => 'Same idea, new angle.', 'tone' => 'recap']]],
        ]], ['lesson' => 'osi-model']));

        $sections = draftSections(osi());
        expect(array_column($sections, 'heading'))->toBe([null, 'Why layers', 'The seven layers', 'Devices by layer', ...$before->slice(2)->pluck('heading')->all(), 'In other words'])
            ->and($sections[1]['uuid'])->toBe($before[0]->uuid)
            ->and($sections[3]['uuid'])->toBe(ContentImporter::sectionUuid('osi-model', 'devices-by-layer'))
            ->and($sections[3]['sources'][0]['kind'])->toBe('rfc')
            ->and(collect($plan->documents[0]['changes'])->countBy('change')->all())->toMatchArray(['added' => 3, 'replaced' => 1])
            // "opening" has no heading, so the next overview will call it "prose-1": the author is told.
            ->and(collect($plan->warnings)->pluck('path'))->toContain('operations.1.section.key')
            ->and(collect($plan->warnings)->pluck('path'))->not->toContain('operations.0.section.key')
            // Learners still see the live lesson: nothing is published.
            ->and(osi()->sections()->pluck('uuid')->all())->toBe($before->pluck('uuid')->all())
            ->and(AdminAuditLog::query()->where('action', 'lesson.package_imported')->value('changes'))->toMatchArray(['kind' => 'lesson-sections', 'generator' => 'Pest']);
    });

    it('refuses a key and a uuid that name different sections', function () {
        $uuid = osi()->sections()->orderBy('position')->value('uuid');
        $plan = planOf(envelope('lesson-sections', ['operations' => [['op' => 'remove', 'key' => 'the-seven-layers', 'uuid' => $uuid]]], ['lesson' => 'osi-model']));

        expect($plan->errors['operations.0'][0])->toContain('two different sections');
    });

    it('explains a stale key differently when the lesson changed since the overview', function () {
        $fingerprint = exported(osi())['base']['lesson_fingerprint'];
        $op = [['op' => 'remove', 'key' => 'the-7-layers']];

        $fresh = planOf(envelope('lesson-sections', ['operations' => $op, 'base' => ['lesson_fingerprint' => $fingerprint]], ['lesson' => 'osi-model']));
        $old = planOf(envelope('lesson-sections', ['operations' => $op, 'base' => ['lesson_fingerprint' => 'aaaaaaaaaaaaaaaa']], ['lesson' => 'osi-model']));

        expect($fresh->errors['operations.0.key'][0])->toContain('keys are not guessed')
            ->and($fresh->warnings)->not->toContain(fn ($w) => $w['path'] === 'base')
            ->and($old->errors['operations.0.key'][0])->toContain('heading may have been renamed')
            ->and(collect($old->warnings)->pluck('path'))->toContain('base');
    });

    it('never reuses a key or accepts an invented uuid', function () {
        $foreign = LessonSection::query()->whereHas('lesson', fn ($q) => $q->where('slug', 'cia-triad'))->value('uuid');
        $plan = planOf(envelope('lesson-sections', ['operations' => [
            ['op' => 'append', 'section' => ['key' => 'the-seven-layers', 'type' => 'prose', 'payload' => ['text' => 'x']]],
            ['op' => 'append', 'section' => ['uuid' => (string) Str::uuid(), 'type' => 'prose', 'payload' => ['text' => 'x']]],
            ['op' => 'replace', 'uuid' => $foreign, 'section' => ['type' => 'prose', 'payload' => ['text' => 'x']]],
        ]], ['lesson' => 'osi-model']));

        expect($plan->errors['operations.0.section.key'][0])->toContain('already used')
            ->and($plan->errors['operations.1.section.uuid'][0])->toContain('no uuid')
            ->and($plan->errors['operations.2.uuid'][0])->toContain('never invent');
    });

    it('reports every problem in one round, not one kind at a time', function () {
        $plan = planOf(envelope('lesson-sections', ['operations' => [
            ['op' => 'remove', 'key' => 'the-7-layers'],
            ['op' => 'append', 'section' => ['type' => 'marquee', 'payload' => []]],
            ['op' => 'append', 'section' => ['type' => 'prose', 'payload' => ['text' => '']]],
        ]], ['lesson' => 'osi-model']));

        expect(array_keys($plan->errors))->toContain('operations.0.key', 'operations.1.section.type', 'operations.2.section.payload.text');
    });

    it('reports at most 100 errors and counts the rest', function () {
        $operations = array_map(fn ($i) => ['op' => 'remove', 'key' => "missing-{$i}"], range(1, 150));
        $report = planOf(envelope('lesson-sections', ['operations' => $operations], ['lesson' => 'osi-model']))->toArray();

        expect(collect($report['errors'])->flatten())->toHaveCount(100)
            ->and($report['errors'])->toHaveKey('operations.99.key')->not->toHaveKey('operations.100.key')
            ->and($report['more_errors'])->toBe(50);
    });

    it('refuses to remove a section an exercise question teaches (D30)', function () {
        $question = ExerciseQuestion::query()->whereNotNull('lesson_section_id')->where('is_archived', false)->with('section.lesson')->firstOrFail();
        $plan = planOf(envelope('lesson-sections', ['operations' => [['op' => 'remove', 'uuid' => $question->section->uuid]]], ['lesson' => $question->section->lesson->slug]));

        expect($plan->errors['operations'][0])->toContain('teaches exercise questions')->toContain($question->key);
    });

    it('refuses to remove a section the lesson architecture points at (D57)', function () {
        $architecture = LessonArchitecture::query()->get()->first(fn ($a) => collect($a->architecture['nodes'])->whereNotNull('section')->isNotEmpty());
        $lesson = Lesson::query()->findOrFail($architecture->lesson_id);
        $referenced = collect($architecture->architecture['nodes'])->whereNotNull('section')->pluck('section')->all();
        $questions = array_keys(app(ExerciseReferences::class)->forLesson($lesson->id));
        $uuid = collect($referenced)->first(fn ($u) => ! in_array($u, $questions, true)) ?? $referenced[0];

        $plan = planOf(envelope('lesson-sections', ['operations' => [['op' => 'remove', 'uuid' => $uuid]]], ['lesson' => $lesson->slug]));

        expect(collect($plan->errors['operations'])->implode(' '))->toContain('architecture');
    });

    it('makes a seeded lesson database-owned, so content:import skips it (D19)', function () {
        $plan = planOf(envelope('lesson-sections', ['operations' => [['op' => 'append', 'section' => ['type' => 'prose', 'payload' => ['text' => 'x']]]]], ['lesson' => 'osi-model']));
        expect(collect($plan->warnings)->pluck('message')->implode(' '))->toContain('content:export');

        importPkg(envelope('lesson-sections', ['operations' => [['op' => 'append', 'section' => ['type' => 'prose', 'payload' => ['text' => 'Imported.']]]]], ['lesson' => 'osi-model']));
        $report = app(ContentImporter::class)->import([require database_path('seeders/content/courses/networking-fundamentals.php')]);

        expect($report['skipped'])->toContain('osi-model')
            ->and(collect(draftSections(osi()))->last()['payload']['text'])->toBe('Imported.');
    });

    it('refuses when the draft changed since the preview', function () {
        $pkg = envelope('lesson-sections', ['operations' => [['op' => 'append', 'section' => ['type' => 'prose', 'payload' => ['text' => 'x']]]]], ['lesson' => 'osi-model']);
        importPkg($pkg);

        importPkg($pkg, null, ['lesson:osi-model' => 0]);
    })->throws(ConflictHttpException::class);

    it('warns about plain http links and factual blocks without sources', function () {
        $plan = planOf(envelope('lesson-sections', ['operations' => [
            ['op' => 'append', 'section' => ['type' => 'prose', 'payload' => ['text' => 'A fact.']]],
            ['op' => 'append', 'section' => ['type' => 'knowledge-check', 'payload' => BlockRegistry::default()->get('knowledge-check')->example()]],
            ['op' => 'append', 'section' => ['type' => 'prose', 'payload' => ['text' => 'y'], 'sources' => [['label' => 'Old page', 'url' => 'http://example.com', 'kind' => 'other']]]],
            ['op' => 'append', 'section' => ['type' => 'callout', 'payload' => ['text' => 'Said another way.', 'tone' => 'recap']]],
            ['op' => 'append', 'section' => ['type' => 'callout', 'payload' => ['text' => 'Like an assembly line.', 'tone' => 'fun-fact']]],
            ['op' => 'append', 'section' => ['type' => 'callout', 'payload' => ['text' => 'Port 22 is SSH.', 'tone' => 'info']]],
        ]], ['lesson' => 'osi-model']));
        $paths = collect($plan->warnings)->pluck('path');
        $funFact = collect($plan->warnings)->firstWhere('path', 'operations.4.section')['message'] ?? '';

        expect($plan->valid())->toBeTrue()
            ->and($paths)->toContain('operations.0.section', 'operations.2.section.sources.0.url', 'operations.4.section', 'operations.5.section')
            // A knowledge check never states facts, and a recap restates.
            ->and($paths)->not->toContain('operations.1.section')
            ->and($paths)->not->toContain('operations.3.section')
            ->and($funFact)->toContain('An analogy needs no source')->toContain('an anecdote');
    });

    it('puts source errors on the section', function () {
        $plan = planOf(envelope('lesson-sections', ['operations' => [
            ['op' => 'append', 'section' => ['type' => 'prose', 'payload' => ['text' => 'y'], 'sources' => [['label' => 'x', 'url' => 'ftp://x', 'kind' => 'docs']]]],
        ]], ['lesson' => 'osi-model']));

        expect(array_keys($plan->errors))->toBe(['operations.0.section.sources.0.url']);
    });
});

describe('whole documents', function () {
    it('adds a new lesson as a draft in the chapter, on no roadmap topic (D62)', function () {
        $plan = importPkg(newLessonPackage('pkg-new-lesson', [['key' => 'intro', 'type' => 'prose', 'heading' => 'Intro', 'payload' => ['text' => 'Hello.']]]));
        $lesson = Lesson::query()->where('slug', 'pkg-new-lesson')->firstOrFail();

        expect($plan->documents[0]['id'])->toBe($lesson->id)
            ->and($lesson->status)->toBe(LessonStatus::Draft)
            ->and($lesson->chapter->title)->toBe('How networks are built')
            ->and($lesson->topics()->exists())->toBeFalse()
            ->and($lesson->draft->snapshot['title'])->toBe('Package lesson')
            ->and($lesson->draft->snapshot['estimated_minutes'])->toBe(12)
            ->and(collect($plan->warnings)->pluck('message')->implode(' '))->toContain('roadmap topic')->toContain('course map');
    });

    it('matches an existing lesson\'s sections by key and refuses dropping protected ones', function () {
        $pkg = exported(osi());
        $pkg['document']['title'] = 'The OSI model, revised';
        $pkg['document']['sections'][0]['heading'] = 'Why we use layers';
        importPkg($pkg);

        $sections = draftSections(osi());
        expect(osi()->draft->snapshot['title'])->toBe('The OSI model, revised')
            ->and(array_column($sections, 'uuid'))->toBe(osi()->sections()->orderBy('position')->pluck('uuid')->all());
    });

    it('names a field error by its package name', function () {
        $plan = planOf(newLessonPackage('pkg-bad-meta', [], ['minutes' => 999, 'title' => '']));

        expect(array_keys($plan->errors))->toContain('document.minutes', 'document.title');
    });

    it('imports a new post and appends to it', function () {
        importPkg(envelope('post', ['document' => [
            'slug' => 'pkg-post', 'title' => 'Package post', 'summary' => 'S', 'tags' => ['docker'], 'cover' => null,
            'sections' => [['key' => 'one', 'type' => 'prose', 'payload' => ['text' => 'One.']]],
        ]]));
        $post = Post::query()->where('slug', 'pkg-post')->firstOrFail();
        importPkg(envelope('post-sections', ['operations' => [['op' => 'append', 'section' => ['key' => 'two', 'type' => 'callout', 'payload' => ['text' => 'Fun.', 'tone' => 'fun-fact']]]]], ['post' => 'pkg-post']));

        expect($post->status)->toBe(LessonStatus::Draft)
            ->and(array_column(draftSections($post), 'uuid'))->toBe([ContentImporter::postSectionUuid('pkg-post', 'one'), ContentImporter::postSectionUuid('pkg-post', 'two')]);
    });

    it('exports only the chosen sections as replace operations that import back', function () {
        $pkg = exported(osi(), ['the-seven-layers']);

        $byUuid = exported(osi(), [osi()->sections()->where('heading', 'The seven layers')->value('uuid')]);
        expect($byUuid['operations'])->toEqual($pkg['operations']);

        expect($pkg['kind'])->toBe('lesson-sections')
            ->and($pkg['operations'])->toHaveCount(1)
            ->and($pkg['operations'][0])->toMatchArray(['op' => 'replace', 'key' => 'the-seven-layers'])
            ->and(planOf($pkg)->valid())->toBeTrue();
    });
});

describe('zip bundles', function () {
    it('reads $file texts, $file documents and images', function () {
        $manifest = newLessonPackage('pkg-zip', [
            ['key' => 'long', 'type' => 'prose', 'heading' => 'Long', 'payload' => ['text' => ['$file' => 'texts/long.md']]],
            ['$file' => 'sections/code.json'],
            ['key' => 'figure', 'type' => 'media', 'payload' => ['media' => ['file' => 'media/diagram.png', 'alt' => 'A diagram'], 'caption' => null]],
        ]);
        $file = makeZip([
            'manifest.json' => json_encode($manifest),
            'texts/long.md' => "\u{FEFF}Line one with \"quotes\" and a \\ backslash.\r\nLine two.",
            'sections/code.json' => json_encode(['key' => 'code', 'type' => 'code', 'payload' => ['code' => ['$file' => 'texts/script.sh'], 'language' => 'bash']]),
            'texts/script.sh' => "echo \"hi\"\n",
            'media/diagram.png' => pngBytes(),
        ]);

        $package = app(PackageReader::class)->read($file, 'x.ccpkg.zip');
        try {
            importPkg($package['envelope'], $package['root']);
        } finally {
            PackageReader::cleanup($package['root']);
        }

        $sections = draftSections(Lesson::query()->where('slug', 'pkg-zip')->firstOrFail());
        $media = LessonMedia::query()->where('uuid', $sections[2]['payload']['media'])->firstOrFail();
        expect($sections[0]['payload']['text'])->toBe("Line one with \"quotes\" and a \\ backslash.\nLine two.")
            ->and($sections[1]['payload']['code'])->toBe('echo "hi"')
            ->and($media->alt)->toBe('A diagram')
            ->and($media->source)->toBeNull()
            ->and(Storage::disk(config('cybercompass.uploads_disk'))->exists($media->path))->toBeTrue()
            ->and(is_dir($package['root']))->toBeFalse();
    });

    it('imports several lessons from a course package, each into its chapter', function () {
        $lesson = fn (string $slug) => ['slug' => $slug, 'title' => "Lesson {$slug}", 'minutes' => 5, 'summary' => 'S', 'objectives' => [], 'takeaways' => [], 'resources' => [],
            'sections' => [['key' => 'intro', 'heading' => 'Intro', 'type' => 'prose', 'payload' => ['text' => 'Hi.']]]];
        $file = makeZip([
            'manifest.json' => json_encode(envelope('course', ['documents' => [
                ['chapter' => 1, 'document' => $lesson('pkg-course-a')],
                ['chapter' => 'How networks are built', 'document' => ['$file' => 'lessons/b.json']],
                ['chapter' => 'No such chapter', 'document' => $lesson('pkg-course-c')],
            ]], ['course' => 'networking-fundamentals'])),
            'lessons/b.json' => json_encode($lesson('pkg-course-b')),
        ]);
        $package = app(PackageReader::class)->read($file, 'course.ccpkg.zip');
        try {
            $plan = planOf($package['envelope'], $package['root']);
            expect(array_keys($plan->errors))->toBe(['documents.2.chapter']);

            $package['envelope']['documents'][2]['chapter'] = 1;
            importPkg($package['envelope'], $package['root']);
        } finally {
            PackageReader::cleanup($package['root']);
        }

        expect(Lesson::query()->whereIn('slug', ['pkg-course-a', 'pkg-course-b', 'pkg-course-c'])->where('status', LessonStatus::Draft)->count())->toBe(3);
    });

    it('refuses entries that leave the package (zip slip)', function (string $name) {
        $errors = readerErrors(fn () => app(PackageReader::class)->read(makeZip(['manifest.json' => '{}', $name => 'x']), 'x.zip'));

        expect(collect($errors)->flatten()->implode(' '))->toContain('stay inside the package');
    })->with(['../evil.md', 'a/../../evil.md', '/etc/evil.md', 'C:/evil.md', 'a\\evil.md']);

    it('refuses a zip bomb', function () {
        $errors = readerErrors(fn () => app(PackageReader::class)->read(makeZip(['manifest.json' => '{}', 'texts/zeros.txt' => str_repeat("\0", 8 * 1024 * 1024)]), 'x.zip'));

        expect(collect($errors)->flatten()->implode(' '))->toContain('zip bomb');
    });

    it('refuses too many entries, unknown file types, a missing manifest and oversized unpacking', function () {
        config(['cybercompass.content_packages.max_entries' => 3, 'cybercompass.content_packages.max_uncompressed_bytes' => 1000]);
        $read = fn (array $entries) => collect(readerErrors(fn () => app(PackageReader::class)->read(makeZip($entries), 'x.zip')))->flatten()->implode(' ');

        expect($read(['manifest.json' => '{}', 'a.md' => 'a', 'b.md' => 'b', 'c.md' => 'c']))->toContain('at most 3')
            ->and($read(['manifest.json' => '{}', 'run.exe' => 'x']))->toContain('.exe')
            ->and($read(['texts/a.md' => 'x']))->toContain('manifest.json')
            ->and($read(['manifest.json' => '{}', 'big.txt' => random_bytes(2000)]))->toContain('larger than');
    });

    it('refuses $file and images outside a zip, and deep or invalid JSON', function () {
        $reader = app(PackageReader::class);
        $plan = planOf(newLessonPackage('pkg-json-image', [['type' => 'media', 'payload' => ['media' => ['file' => 'media/a.png', 'alt' => 'a']]]]));

        expect(readerErrors(fn () => $reader->fromString(json_encode(['kind' => ['$file' => 'x.md']])))['kind'][0])->toContain('only inside a .ccpkg.zip')
            ->and($plan->errors['document.sections.0.payload.media'][0])->toContain('zip bundle')
            ->and(readerErrors(fn () => $reader->fromString(str_repeat('[', 70).str_repeat(']', 70)))['package'][0])->toContain('not valid JSON')
            ->and(readerErrors(fn () => $reader->fromString('{"a": "unterminated}'))['package'][0])->toContain('escaped line breaks');
    });

    it('reads a large package of tens of megabytes', function () {
        // Incompressible bytes, so the zip really is large; the reader unpacks it entry by entry.
        $entries = ['manifest.json' => json_encode(newLessonPackage('pkg-large', [['key' => 'a', 'type' => 'prose', 'payload' => ['text' => 'Large.']]]))];
        for ($i = 0; $i < 6; $i++) {
            $entries["media/filler-{$i}.png"] = random_bytes(5 * 1024 * 1024);
        }
        $file = makeZip($entries);
        expect(filesize($file))->toBeGreaterThan(30 * 1024 * 1024);

        $package = app(PackageReader::class)->read($file, 'x.ccpkg.zip');
        try {
            expect(planOf($package['envelope'], $package['root'])->valid())->toBeTrue();
        } finally {
            PackageReader::cleanup($package['root']);
            @unlink($file);
        }
    });
});

describe('the command', function () {
    it('validates, imports and exports', function () {
        $file = tempnam(sys_get_temp_dir(), 'pkg').'.ccpkg.json';
        file_put_contents($file, json_encode(envelope('lesson-sections', ['operations' => [['op' => 'append', 'section' => ['key' => 'cli', 'heading' => 'Cli', 'type' => 'prose', 'payload' => ['text' => 'From the CLI.']]]]], ['lesson' => 'osi-model'])));

        $this->artisan('content:package', ['action' => 'validate', 'file' => $file])->expectsOutputToContain('added    cli')->assertSuccessful();
        expect(osi()->draft)->toBeNull();

        $this->artisan('content:package', ['action' => 'import', 'file' => $file, '--as' => User::factory()->create()->email])->assertFailed();
        $this->artisan('content:package', ['action' => 'import', 'file' => $file, '--as' => $this->admin->email])->assertSuccessful();
        expect(collect(draftSections(osi()))->last()['payload']['text'])->toBe('From the CLI.');

        $this->artisan('content:package', ['action' => 'export', '--lesson' => 'osi-model', '--sections' => 'cli'])->expectsOutputToContain('"op": "replace"')->assertSuccessful();
        @unlink($file);
    });

    it('prints errors by place and fails', function () {
        $file = tempnam(sys_get_temp_dir(), 'pkg').'.ccpkg.json';
        file_put_contents($file, json_encode(envelope('lesson-sections', ['operations' => [['op' => 'remove', 'key' => 'nope']]], ['lesson' => 'osi-model'])));

        $this->artisan('content:package', ['action' => 'validate', 'file' => $file])->expectsOutputToContain('operations.0.key')->assertFailed();
        @unlink($file);
    });
});

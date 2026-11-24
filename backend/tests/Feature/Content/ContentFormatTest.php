<?php

use App\Models\Course;
use App\Models\ExerciseQuestion;
use App\Models\Lesson;
use App\Models\LessonDraft;
use App\Models\User;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Packages\CourseOverview;
use App\Modules\Content\Packages\FormatGuide;
use App\Modules\Content\Packages\PackagePlanner;
use App\Modules\Content\Services\LessonWorkingCopy;

/*
| F6 Part E (D71): the AI authoring guide is generated and current, the course overview tells a
| chatbot what exists (and what is missing), and the committed example package is valid.
*/

function networkingOverview(): array
{
    return app(CourseOverview::class)->build(Course::query()->where('slug', 'networking-fundamentals')->firstOrFail());
}

function overviewLesson(array $overview, string $slug): array
{
    return collect($overview['chapters'])->flatMap(fn ($c) => $c['lessons'])->firstWhere('slug', $slug);
}

describe('the AI authoring guide', function () {
    it('is current: regenerate it with php artisan content:format-guide', function () {
        expect(file_get_contents(FormatGuide::path('AI_AUTHORING_GUIDE.md')))->toBe(app(FormatGuide::class)->render());
        $this->artisan('content:format-guide', ['--check' => true])->assertSuccessful();
    });

    it('describes every registered block type with its schema and an example, and the rules that matter', function () {
        $guide = app(FormatGuide::class)->render();

        foreach (array_keys(BlockRegistry::default()->all()) as $type) {
            expect($guide)->toContain("### `{$type}`:")->toContain("\"type\": \"{$type}\"");
        }
        expect($guide)->not->toContain('<!-- generated:')
            ->toContain('name it by its **`uuid`**')
            ->toContain('Give a **`key` only to a new section.**')
            ->toContain('Never invent a citation')
            ->toContain('"tone": "recap"')
            ->toContain('An analogy needs no source. An anecdote does')
            ->toContain('| Sections in a lesson | '.Lesson::MAX_SECTIONS.' |')
            ->toContain('| `callout` | Callout | when `tone` is info, warning or fun-fact: cite sources |')
            ->toContain('| `prose` | Text | yes: cite sources |')
            ->toContain('| `code` | Code | no |');
    });

    it('is served to admins', function () {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get('/api/v1/admin/content/packages/guide')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="AI_AUTHORING_GUIDE.md"')
            ->assertSee('# CyberCompass AI authoring guide', false);
        Auth::forgetGuards();
        $this->actingAs(User::factory()->create(), 'web')->get('/api/v1/admin/content/packages/guide')->assertForbidden();
    });
});

describe('the course overview', function () {
    it('lists every section with its key and uuid, and makes the OSI gaps obvious', function () {
        $osi = overviewLesson(networkingOverview(), 'osi-model');
        $layers = collect($osi['sections'])->firstWhere('key', 'the-seven-layers');

        expect($layers['uuid'])->toBe(Lesson::query()->where('slug', 'osi-model')->first()->sections()->where('heading', 'The seven layers')->value('uuid'))
            ->and($osi['unpublished_draft'])->toBeFalse()
            ->and($osi['objectives'][0]['hint'])->toContain('the-seven-layers')
            ->and($osi['objectives'][1]['hint'])->toBe([])
            ->and($osi['objectives'][2]['hint'])->toBe([])
            ->and($osi['blocks_unused'])->toContain('comparison')
            ->and(collect($osi['architecture']['nodes'])->pluck('section_key')->unique()->all())->toBe(['the-seven-layers']);
    });

    it('marks a lesson whose draft differs from what learners see', function () {
        $lesson = Lesson::query()->where('slug', 'subnetting')->firstOrFail();
        $snapshot = app(LessonWorkingCopy::class)->fromLive($lesson);
        $snapshot['sections'][0]['heading'] = 'Draft-only heading';
        LessonDraft::query()->create(['lesson_id' => $lesson->id, 'lock_version' => 1, 'snapshot' => $snapshot, 'updated_by' => User::factory()->admin()->create()->id]);

        $overview = networkingOverview();
        $markdown = CourseOverview::markdown($overview);

        expect(overviewLesson($overview, 'subnetting')['unpublished_draft'])->toBeTrue()
            ->and(overviewLesson($overview, 'subnetting')['sections'][0]['heading'])->toBe('Draft-only heading')
            ->and($markdown)->toContain('**draft – not yet published**')
            ->and(substr_count($markdown, 'draft – not yet published'))->toBe(1);
    });

    it('carries no graded exercise answers', function () {
        $markdown = CourseOverview::markdown(networkingOverview());
        $explanations = ExerciseQuestion::query()->whereHas('exercise.lesson', fn ($q) => $q->where('slug', 'osi-model'))->pluck('explanation');

        expect($explanations)->not->toBeEmpty();
        foreach ($explanations as $explanation) {
            expect($markdown)->not->toContain($explanation);
        }
    });

    it('is exported as Markdown or JSON to admins only', function () {
        $course = Course::query()->where('slug', 'networking-fundamentals')->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'web')->get("/api/v1/admin/content/courses/{$course->id}/overview")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="networking-fundamentals.overview.md"')
            ->assertSee('# Course overview: Networking Fundamentals', false);
        $json = $this->actingAs($admin, 'web')->get("/api/v1/admin/content/courses/{$course->id}/overview?format=json")->assertOk();
        expect(json_decode($json->getContent(), true)['course']['slug'])->toBe('networking-fundamentals');

        Auth::forgetGuards();
        $this->actingAs(User::factory()->create(), 'web')->get("/api/v1/admin/content/courses/{$course->id}/overview")->assertForbidden();
    });
});

describe('the OSI example', function () {
    it('is a valid package that fills the gaps against the seeded lesson', function () {
        $package = json_decode(file_get_contents(FormatGuide::path('examples/osi-model.additions.ccpkg.json')), true);
        $plan = app(PackagePlanner::class)->plan($package, null);

        expect($plan->errors)->toBe([])
            ->and(collect($plan->documents[0]['changes'])->where('change', 'added')->pluck('key')->all())
            ->toBe(['like-the-postal-system', 'devices-and-protocols-by-layer', 'finding-the-layer-of-an-attack', 'attacks-and-defences-by-layer', 'callout-9'])
            // No stale fingerprint and no factual section without sources. The fun fact is an analogy,
            // so its warning is the one an author may ignore; the other is the D19 ownership note.
            ->and(collect($plan->warnings)->pluck('path')->all())->toBe(['operations.0.section', 'osi-model'])
            ->and($plan->warnings[0]['message'])->toContain('An analogy needs no source');
    });

    it('was generated from the overview command, with the gaps it was written for', function () {
        $overview = file_get_contents(FormatGuide::path('examples/networking-fundamentals.overview.md'));

        expect($overview)->toContain('# Course overview: Networking Fundamentals')
            ->toContain('2. Place common protocols and devices on the right layer → **no dedicated section found**')
            ->toContain('3. Use the model to reason about where an attack happens → **no dedicated section found**')
            ->toContain('`1c6a50ea-28ea-5d3e-8916-a44731e938fe`')
            ->not->toContain('e2e-');
    });
});

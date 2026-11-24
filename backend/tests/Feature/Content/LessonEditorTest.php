<?php

use App\Enums\LessonStatus;
use App\Models\AdminAuditLog;
use App\Models\Lesson;
use App\Models\LessonDraft;
use App\Models\LessonRevision;
use App\Models\User;
use App\Modules\Content\Services\ContentImporter;

/*
| The lesson editor (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §5.1): all edits go to a draft;
| learners see the live lesson until it is published.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $this->learner = User::factory()->create();
    $this->lesson = Lesson::query()->where('slug', 'hashing-and-passwords')->firstOrFail();
});

function editor(Lesson $lesson): array
{
    Auth::forgetGuards(); // the sanctum guard caches the first acting user of a test

    return test()->actingAs(test()->admin, 'web')->getJson("/api/v1/admin/content/lessons/{$lesson->id}")->assertOk()->json('data');
}

/** The editor's working copy as a save body. */
function body(array $data, array $changes = []): array
{
    return [...$data['working_copy'], 'lock_version' => $data['lock_version'], ...$changes];
}

function learnerView(string $slug): array
{
    Auth::forgetGuards();
    $data = test()->actingAs(test()->learner, 'web')->getJson("/api/v1/lessons/{$slug}")->json('data') ?? [];
    Auth::forgetGuards();

    return $data;
}

/** A learner request; the guard is reset around it so the admin can act again afterwards. */
function asLearner(callable $request): mixed
{
    Auth::forgetGuards();
    test()->actingAs(test()->learner, 'web');
    $result = $request();
    Auth::forgetGuards();

    return $result;
}

describe('reading', function () {
    it('returns the live lesson as the working copy, with learner-identical preview sections', function () {
        $data = editor($this->lesson);

        expect($data['lock_version'])->toBe(0)
            ->and($data['has_unpublished_changes'])->toBeFalse()
            ->and($data['slug_editable'])->toBeFalse()
            ->and($data['working_copy']['title'])->toBe($this->lesson->title)
            ->and(collect($data['working_copy']['sections'])->pluck('id')->all())
            ->toBe(collect(learnerView('hashing-and-passwords')['sections'])->pluck('id')->all())
            ->and($data['preview_sections'])->toBe(learnerView('hashing-and-passwords')['sections']);
    });

    it('lists lessons with filters, counts and draft state', function () {
        $data = editor($this->lesson);
        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['title' => 'Drafted title']));

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/content/lessons?filter[has_draft]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Drafted title')
            ->assertJsonPath('data.0.has_unpublished_changes', true)
            ->assertJsonPath('data.0.last_edited_by', 'Ada Admin');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/content/lessons?per_page=50&sort=course')
            ->assertJsonPath('meta.total', Lesson::query()->count());
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/content/lessons?q=tls')
            ->assertJsonPath('data.0.slug', 'tls-overview');
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/content/lessons?filter[status]=archived')
            ->assertJsonCount(0, 'data');
    });

    it('describes every block type with its schema and example', function () {
        $types = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/content/block-types')->assertOk()->json('data'))->keyBy('key');

        expect($types->keys()->all())->toContain('prose', 'callout', 'steps', 'code', 'diagram', 'media', 'layer-stack', 'sequence', 'flow', 'tree', 'topology', 'comparison', 'timeline', 'bitfield', 'calculator', 'step-through', 'knowledge-check')
            ->and($types['prose']['editor'])->toBe('form')
            ->and($types['diagram']['editor'])->toBe('json')
            // OSI and TCP moved to layer-stack and sequence: still valid for old revisions, no longer offered.
            ->and($types['diagram']['diagram_keys'])->toBe(['cia-triad'])
            ->and($types['knowledge-check']['example']['questions'][0]['options'])->toHaveCount(3)
            ->and($types['callout']['schema']['properties']['tone']['enum'])->toBe(['info', 'tip', 'warning', 'recap', 'fun-fact']);
    });
});

describe('drafts', function () {
    it('carries section sources through the draft, the preview and publishing (D68)', function () {
        $data = editor($this->lesson);
        $sections = $data['working_copy']['sections'];
        $sources = [['label' => 'NIST SP 800-63B', 'url' => 'https://pages.nist.gov/800-63-3/sp800-63b.html', 'kind' => 'docs', 'note' => 'Section 5.1.1.2']];
        $sections[0]['sources'] = $sources;

        $saved = $this->actingAs($this->admin, 'web')
            ->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['sections' => $sections]))
            ->assertOk()
            // MySQL JSON reorders keys, so compare as maps.
            ->assertJsonPath('data.working_copy.sections.0.sources', fn ($v) => $v == $sources)
            ->assertJsonPath('data.preview_sections.0.sources', fn ($v) => $v == $sources)
            ->json('data');
        expect(learnerView('hashing-and-passwords')['sections'][0]['sources'])->toBeNull();

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

        expect(learnerView('hashing-and-passwords')['sections'][0]['sources'])->toEqual($sources)
            ->and(learnerView('hashing-and-passwords')['sections'][1]['sources'])->toBeNull();
    });

    it('refuses a source that is not a link', function () {
        $data = editor($this->lesson);
        $sections = $data['working_copy']['sections'];
        $sections[1]['sources'] = [['label' => 'x', 'url' => 'javascript:alert(1)', 'kind' => 'docs']];

        $this->actingAs($this->admin, 'web')
            ->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['sections' => $sections]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections.1.sources.0.url']);
    });

    it('saves edits to a draft that learners never see, and imports leave it alone', function () {
        $before = learnerView('hashing-and-passwords');
        $data = editor($this->lesson);
        $sections = $data['working_copy']['sections'];
        $sections[0]['payload']['text'] = 'Rewritten opening paragraph.';

        $saved = $this->actingAs($this->admin, 'web')
            ->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['title' => 'Hashing, revisited', 'sections' => $sections]))
            ->assertOk()
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.has_unpublished_changes', true)
            ->assertJsonPath('data.working_copy.title', 'Hashing, revisited')
            ->assertJsonPath('data.preview_sections.0.payload.text', 'Rewritten opening paragraph.')
            ->json('data');

        expect(learnerView('hashing-and-passwords'))->toBe($before)
            ->and(collect($saved['working_copy']['sections'])->pluck('id')->all())->toBe(collect($sections)->pluck('id')->all());

        $report = app(ContentImporter::class)->import([require database_path('seeders/content/courses/applied-cryptography.php')]);
        expect($report['skipped'])->toContain('hashing-and-passwords');
    });

    it('rejects a stale lock_version with 409 conflict', function () {
        $data = editor($this->lesson);
        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['title' => 'First']))->assertOk();

        // A second admin still holds lock_version 0.
        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['title' => 'Second']))
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');

        expect(LessonDraft::query()->where('lesson_id', $this->lesson->id)->value('snapshot')['title'])->toBe('First');
    });

    it('reports validation errors at the exact field', function () {
        $data = editor($this->lesson);
        $sections = $data['working_copy']['sections'];
        $sections[1] = ['type' => 'callout', 'heading' => 'Careful', 'payload' => ['text' => '', 'tone' => 'loud']];

        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, [
            'title' => '',
            'resources' => [['label' => 'Insecure', 'url' => 'http://example.com']],
            'sections' => $sections,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'resources.0.url', 'sections.1.payload.text', 'sections.1.payload.tone'])
            // Plain words for authors, not the schema library's wording.
            ->assertJsonPath('errors', fn ($e) => $e['sections.1.payload.text'] === ['This can not be empty.']
                && $e['sections.1.payload.tone'] === ['Choose one of: info, tip, warning, recap, fun-fact.']);
    });

    it('reports a missing field on the field itself', function () {
        $data = editor($this->lesson);

        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, [
            'sections' => [['type' => 'code', 'heading' => null, 'payload' => ['language' => 'bash']]],
        ]))->assertJsonValidationErrors(['sections.0.payload.code' => 'This field is required.']);
    });

    it('stores content exactly as written, keeping code indentation', function () {
        $data = editor($this->lesson);
        $code = "    indented line\n\tand a tab";

        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, [
            'sections' => [['type' => 'code', 'heading' => null, 'payload' => ['code' => $code, 'language' => 'text']]],
        ]))->assertOk()->assertJsonPath('data.working_copy.sections.0.payload.code', $code);
    });

    it('gives new sections an id and refuses ids from other lessons or duplicates', function () {
        $data = editor($this->lesson);
        $foreign = Lesson::query()->where('slug', 'tls-overview')->firstOrFail()->sections()->value('uuid');
        $sections = $data['working_copy']['sections'];

        $saved = $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, [
            'sections' => [...$sections, ['type' => 'prose', 'heading' => 'New', 'payload' => ['text' => 'Added.']]],
        ]))->assertOk()->json('data');
        expect(end($saved['working_copy']['sections'])['id'])->toMatch('/^[0-9a-f-]{36}$/');

        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($saved, [
            'sections' => [['id' => $foreign, 'type' => 'prose', 'heading' => null, 'payload' => ['text' => 'Stolen.']]],
        ]))->assertJsonValidationErrors('sections.0.id');

        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($saved, [
            'sections' => [$sections[0], $sections[0]],
        ]))->assertJsonValidationErrors('sections.1.id');
    });

    it('keeps the slug of a published lesson', function () {
        $data = editor($this->lesson);

        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['slug' => 'renamed']))
            ->assertOk()
            ->assertJsonPath('data.working_copy.slug', 'hashing-and-passwords');
    });

    it('discards a draft and goes back to the live lesson', function () {
        $data = editor($this->lesson);
        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['title' => 'Throwaway']));

        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft")
            ->assertOk()
            ->assertJsonPath('data.has_unpublished_changes', false)
            ->assertJsonPath('data.working_copy.title', $this->lesson->title);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'lesson.draft_discarded', 'subject_id' => $this->lesson->id]);
    });
});

describe('publishing', function () {
    it('makes the draft live with the same section ids, and records a revision', function () {
        $data = editor($this->lesson);
        $sections = $data['working_copy']['sections'];
        $ids = collect($sections)->pluck('id')->all();
        $sections = array_reverse($sections);
        $sections[0]['heading'] = 'Moved to the top';
        $saved = $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['sections' => $sections]))->json('data');

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => $saved['lock_version'], 'note' => 'Reordered'])
            ->assertOk()
            ->assertJsonPath('data.has_unpublished_changes', false)
            ->assertJsonPath('data.lock_version', 0)
            ->assertJsonPath('data.status', 'published');

        $live = learnerView('hashing-and-passwords');
        expect(collect($live['sections'])->pluck('id')->all())->toBe(array_reverse($ids))
            ->and($live['sections'][0]['heading'])->toBe('Moved to the top');

        $revision = LessonRevision::query()->where('lesson_id', $this->lesson->id)->sole();
        expect($revision->version)->toBe(1)->and($revision->note)->toBe('Reordered')
            ->and($this->lesson->refresh()->updated_by)->toBe($this->admin->id)
            ->and(AdminAuditLog::query()->where('action', 'lesson.published')->value('changes'))->toEqual(['version' => 1, 'note' => 'Reordered']);
    });

    it('refuses to publish over someone else\'s newer draft', function () {
        $data = editor($this->lesson);
        $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['title' => 'Newer']));

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => 0])
            ->assertStatus(409);
        expect($this->lesson->refresh()->title)->not->toBe('Newer');
    });

    it('creates a new lesson as an invisible draft, then publishes it at the end of its chapter', function () {
        $chapter = $this->lesson->chapter;
        $created = $this->actingAs($this->admin, 'web')->postJson('/api/v1/admin/content/lessons', [
            'chapter_id' => $chapter->id, 'title' => 'Salting in practice', 'slug' => 'salting-in-practice',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug_editable', true)
            ->assertJsonPath('data.can_delete', true)
            ->json('data');

        asLearner(fn () => $this->getJson('/api/v1/lessons/salting-in-practice')->assertNotFound());
        expect(collect($this->getJson('/api/v1/courses/applied-cryptography')->json('data.chapters.0.lessons'))->pluck('slug'))->not->toContain('salting-in-practice');

        // Publishing needs a summary and at least one section.
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$created['id']}/publish", ['lock_version' => $created['lock_version']])
            ->assertJsonValidationErrors(['summary', 'sections']);

        $saved = $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$created['id']}/draft", body($created, [
            'summary' => 'Why every password gets its own salt.',
            'objectives' => ['Explain what a salt defeats'],
            'sections' => [['type' => 'prose', 'heading' => 'Why salt', 'payload' => ['text' => 'Rainbow tables stop working.']]],
        ]))->assertOk()->json('data');
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$created['id']}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

        asLearner(fn () => $this->getJson('/api/v1/lessons/salting-in-practice')->assertOk());
        expect(collect($this->getJson('/api/v1/courses/applied-cryptography')->json('data.chapters.0.lessons'))->pluck('slug')->last())->toBe('salting-in-practice');
    });

    it('moves a lesson to the end of another chapter when published', function () {
        $target = Lesson::query()->where('slug', 'tls-overview')->firstOrFail()->chapter;
        $data = editor($this->lesson);
        $saved = $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['chapter_id' => $target->id]))->json('data');

        expect($this->lesson->refresh()->course_chapter_id)->not->toBe($target->id); // not until published

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => $saved['lock_version']])->assertOk();
        expect($this->lesson->refresh()->course_chapter_id)->toBe($target->id)
            ->and($target->lessons()->pluck('slug')->last())->toBe('hashing-and-passwords');
    });
});

describe('lifecycle', function () {
    it('archives a lesson and brings it back by publishing', function () {
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/archive")
            ->assertOk()->assertJsonPath('data.status', 'archived');
        asLearner(fn () => $this->getJson('/api/v1/lessons/hashing-and-passwords')->assertNotFound());

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => 0])
            ->assertOk()->assertJsonPath('data.status', 'published');
        asLearner(fn () => $this->getJson('/api/v1/lessons/hashing-and-passwords')->assertOk());
    });

    it('deletes only lessons that never reached learners', function () {
        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/admin/content/lessons/{$this->lesson->id}")
            ->assertStatus(409);

        $created = $this->actingAs($this->admin, 'web')->postJson('/api/v1/admin/content/lessons', [
            'chapter_id' => $this->lesson->course_chapter_id, 'title' => 'Scratch', 'slug' => 'scratch-lesson',
        ])->json('data');

        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/admin/content/lessons/{$created['id']}/draft")
            ->assertUnprocessable(); // nothing to fall back to
        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/admin/content/lessons/{$created['id']}")->assertNoContent();

        $this->assertDatabaseMissing('lessons', ['slug' => 'scratch-lesson']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'lesson.deleted', 'subject_id' => $created['id']]);
    });

    it('validates new lesson slugs', function () {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/admin/content/lessons', [
            'chapter_id' => $this->lesson->course_chapter_id, 'title' => 'X', 'slug' => 'Hashing And Passwords',
        ])->assertJsonValidationErrors('slug');
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/admin/content/lessons', [
            'chapter_id' => $this->lesson->course_chapter_id, 'title' => 'X', 'slug' => 'hashing-and-passwords',
        ])->assertJsonValidationErrors('slug');
    });
});

describe('revisions', function () {
    it('lists versions with what changed, and restores one into the draft only', function () {
        $publish = function (callable $changes) {
            $data = editor($this->lesson);
            $saved = $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, $changes($data)))->json('data');
            $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/publish", ['lock_version' => $saved['lock_version']])->assertOk();
        };
        $originalTitle = $this->lesson->title;
        $publish(fn () => []);
        $publish(fn ($d) => ['title' => 'Second title', 'sections' => array_slice($d['working_copy']['sections'], 1)]);

        $revisions = $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/content/lessons/{$this->lesson->id}/revisions")->assertOk()->json('data');
        expect($revisions[0]['version'])->toBe(2)
            ->and($revisions[0]['author'])->toBe('Ada Admin')
            ->and($revisions[0]['changes']['fields'])->toBe(['title'])
            ->and($revisions[0]['changes']['sections']['removed'])->toBe(1)
            ->and($revisions[1]['changes']['initial'])->toBeTrue();

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/revisions/1/restore", ['lock_version' => 0])
            ->assertOk()
            ->assertJsonPath('data.working_copy.title', $originalTitle)
            ->assertJsonPath('data.has_unpublished_changes', true);
        expect($this->lesson->refresh()->title)->toBe('Second title');

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/lessons/{$this->lesson->id}/revisions/9/restore", ['lock_version' => 1])
            ->assertNotFound();
    });
});

it('keeps learners out of drafts of an archived lesson too', function () {
    $this->lesson->update(['status' => LessonStatus::Archived]);
    $data = editor($this->lesson);
    $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/lessons/{$this->lesson->id}/draft", body($data, ['title' => 'Still hidden']))->assertOk();

    asLearner(fn () => $this->getJson('/api/v1/lessons/hashing-and-passwords')->assertNotFound());
});

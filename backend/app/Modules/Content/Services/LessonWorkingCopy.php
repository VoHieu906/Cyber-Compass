<?php

namespace App\Modules\Content\Services;

use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Modules\Content\CourseMap\MapSchemas;
use App\Modules\Content\Documents\BlockDocument;
use App\Modules\Content\Documents\WorkingCopy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The lesson an admin is editing: the draft if one exists, otherwise the live content.
 * Drafts and revisions store the same snapshot shape:
 *
 *   { chapter_id, slug, title, summary, estimated_minutes, objectives[], key_takeaways[],
 *     resources[{label, url}], sections[{uuid, type, heading, payload}], architecture }
 *
 * validate() is the only way a snapshot is built from request input, so everything
 * stored in a draft has passed the same rules as seeded content (ARCHITECTURE D17, D18).
 * The section rules are shared with posts (WorkingCopy, D55). `architecture` (F3, D57) is
 * validated against this same snapshot's sections, so a section a sub-node points at cannot be
 * removed until the reference is cleared; a snapshot without the key (older revisions) has none.
 */
final class LessonWorkingCopy extends WorkingCopy
{
    public const MAX_SECTIONS = Lesson::MAX_SECTIONS;

    /** @param  Lesson  $doc */
    public function fromLive(BlockDocument $doc): array
    {
        return [
            'chapter_id' => $doc->course_chapter_id,
            'slug' => $doc->slug,
            'title' => $doc->title,
            'summary' => $doc->summary,
            'estimated_minutes' => $doc->estimated_minutes,
            'objectives' => $doc->objectives ?? [],
            'key_takeaways' => $doc->key_takeaways ?? [],
            'resources' => $doc->resources ?? [],
            'sections' => $this->liveSections($doc),
            'architecture' => $doc->loadMissing('architecture')->architecture?->architecture,
        ];
    }

    /**
     * Sections without an `id` get a new UUID. Error keys match the request body:
     * `title`, `resources.1.url`, `sections.3.payload.items.0`.
     *
     * @param  Lesson  $doc
     */
    public function validate(BlockDocument $doc, array $input): array
    {
        $slugEditable = $doc->published_at === null;

        $validator = Validator::make($input, [
            'chapter_id' => ['required', 'integer', Rule::exists('course_chapters', 'id')],
            'slug' => $slugEditable
                ? ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('lessons', 'slug')->ignore($doc->id)]
                : ['nullable', 'string'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:300'],
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'objectives' => ['present', 'array', 'max:10'],
            'objectives.*' => ['required', 'string', 'max:200'],
            'key_takeaways' => ['present', 'array', 'max:10'],
            'key_takeaways.*' => ['required', 'string', 'max:200'],
            'resources' => ['present', 'array', 'max:10'],
            'resources.*.label' => ['required', 'string', 'max:120'],
            'resources.*.url' => ['required', 'string', 'max:500', 'url:https'],
            ...$this->sectionRules($doc),
        ], [
            'slug.regex' => __('Use lowercase letters, numbers and single hyphens.'),
            'resources.*.url.url' => __('Links must start with https://.'),
        ]);

        $errors = $validator->errors()->toArray();
        [$sectionErrors, $sections] = $this->validateSections($doc, $input);
        $errors += $sectionErrors;
        // While blocks are invalid there is no clean list, so check references against the ids sent;
        // otherwise every architecture node would also be reported as pointing nowhere.
        $uuids = $sectionErrors
            ? array_values(array_filter(array_map(fn ($s) => is_array($s) && is_string($s['id'] ?? null) ? strtolower($s['id']) : null, (array) ($input['sections'] ?? []))))
            : array_column($sections, 'uuid');
        [$archErrors, $architecture] = MapSchemas::architecture($input['architecture'] ?? null, $uuids);
        $errors += $archErrors;

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $trimList = fn (array $items) => array_values(array_map(fn ($s) => trim((string) $s), $items));

        return [
            'chapter_id' => (int) $input['chapter_id'],
            'slug' => $slugEditable ? (string) $input['slug'] : $doc->slug,
            'title' => trim((string) $input['title']),
            'summary' => trim((string) ($input['summary'] ?? '')),
            'estimated_minutes' => (int) $input['estimated_minutes'],
            'objectives' => $trimList($input['objectives']),
            'key_takeaways' => $trimList($input['key_takeaways']),
            'resources' => array_values(array_map(fn ($r) => ['label' => trim((string) $r['label']), 'url' => trim((string) $r['url'])], $input['resources'])),
            'sections' => $sections,
            'architecture' => self::withoutLinksLeaving($architecture, (int) $input['chapter_id']),
        ];
    }

    /**
     * Sub-node links to lessons of another course are dropped (a lesson moved to another course
     * keeps its architecture, not its old neighbours). Links to unknown or hidden lessons stay:
     * readers never see them (CourseMap) and the editor lists them as issues.
     *
     * @param  array<string, mixed>|null  $architecture
     * @return array<string, mixed>|null
     */
    private static function withoutLinksLeaving(?array $architecture, int $chapterId): ?array
    {
        $linked = array_filter(array_column($architecture['nodes'] ?? [], 'lesson'));
        if ($linked === []) {
            return $architecture;
        }
        $courseId = CourseChapter::query()->whereKey($chapterId)->value('course_id');
        $elsewhere = array_flip(Lesson::query()->whereIn('slug', $linked)
            ->whereHas('chapter', fn ($q) => $q->where('course_id', '!=', $courseId))
            ->pluck('slug')->all());
        foreach ($architecture['nodes'] as &$node) {
            if (isset($node['lesson'], $elsewhere[$node['lesson']])) {
                unset($node['lesson']);
            }
        }
        unset($node);

        return $architecture;
    }
}

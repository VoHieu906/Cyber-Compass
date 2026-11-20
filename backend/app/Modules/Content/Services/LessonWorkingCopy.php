<?php

namespace App\Modules\Content\Services;

use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonSection;
use App\Modules\Content\Blocks\BlockRegistry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The lesson an admin is editing: the draft if one exists, otherwise the live content.
 * Drafts and revisions store the same snapshot shape:
 *
 *   { chapter_id, slug, title, summary, estimated_minutes, objectives[], key_takeaways[],
 *     resources[{label, url}], sections[{uuid, type, heading, payload}] }
 *
 * validate() is the only way a snapshot is built from request input, so everything
 * stored in a draft has passed the same rules as seeded content (ARCHITECTURE D17, D18).
 */
final class LessonWorkingCopy
{
    public const MAX_SECTIONS = 60;

    public function __construct(private readonly BlockRegistry $blocks) {}

    /** @return array<string, mixed> */
    public function fromLive(Lesson $lesson): array
    {
        return [
            'chapter_id' => $lesson->course_chapter_id,
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'summary' => $lesson->summary,
            'estimated_minutes' => $lesson->estimated_minutes,
            'objectives' => $lesson->objectives ?? [],
            'key_takeaways' => $lesson->key_takeaways ?? [],
            'resources' => $lesson->resources ?? [],
            'sections' => $lesson->sections()->get()
                ->map(fn (LessonSection $s) => ['uuid' => $s->uuid, 'type' => $s->type, 'heading' => $s->heading, 'payload' => $s->payload])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function current(Lesson $lesson): array
    {
        return $lesson->draft?->snapshot ?? $this->fromLive($lesson);
    }

    /** The version a client must send to change the working copy: 0 while there is no draft. */
    public function lockVersion(Lesson $lesson): int
    {
        return $lesson->draft?->lock_version ?? 0;
    }

    /**
     * Section uuids this lesson may use: its live sections, its draft and its revisions.
     * Any other id in a request is rejected, so a section can never be moved out of another lesson.
     *
     * @return list<string>
     */
    public function knownUuids(Lesson $lesson): array
    {
        $uuids = $lesson->sections()->pluck('uuid')->all();
        foreach ([$lesson->draft?->snapshot, ...$lesson->revisions()->pluck('snapshot')->all()] as $snapshot) {
            foreach ($snapshot['sections'] ?? [] as $section) {
                $uuids[] = $section['uuid'];
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * Validates input (request body or a revision snapshot) into a snapshot.
     * Sections without an `id` get a new UUID. Error keys match the request body:
     * `title`, `resources.1.url`, `sections.3.payload.items.0`.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Lesson $lesson, array $input): array
    {
        $slugEditable = $lesson->published_at === null;

        $validator = Validator::make($input, [
            'chapter_id' => ['required', 'integer', Rule::exists('course_chapters', 'id')],
            'slug' => $slugEditable
                ? ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('lessons', 'slug')->ignore($lesson->id)]
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
            'sections' => ['present', 'array', 'max:'.self::MAX_SECTIONS],
            'sections.*' => ['array'],
            'sections.*.id' => ['nullable', 'uuid'],
        ], [
            'slug.regex' => __('Use lowercase letters, numbers and single hyphens.'),
            'resources.*.url.url' => __('Links must start with https://.'),
        ]);

        $errors = $validator->errors()->toArray();

        $sections = array_values(is_array($input['sections'] ?? null) ? $input['sections'] : []);
        $clean = [];
        try {
            $clean = $this->blocks->validateSections($sections);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }

        $errors += $this->checkIds($lesson, $sections);
        $errors += $this->checkMedia($clean);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $trimList = fn (array $items) => array_values(array_map(fn ($s) => trim((string) $s), $items));

        return [
            'chapter_id' => (int) $input['chapter_id'],
            'slug' => $slugEditable ? (string) $input['slug'] : $lesson->slug,
            'title' => trim((string) $input['title']),
            'summary' => trim((string) ($input['summary'] ?? '')),
            'estimated_minutes' => (int) $input['estimated_minutes'],
            'objectives' => $trimList($input['objectives']),
            'key_takeaways' => $trimList($input['key_takeaways']),
            'resources' => array_values(array_map(fn ($r) => ['label' => trim((string) $r['label']), 'url' => trim((string) $r['url'])], $input['resources'])),
            'sections' => array_map(fn (array $s, int $i) => [
                'uuid' => is_string($sections[$i]['id'] ?? null) ? strtolower($sections[$i]['id']) : (string) Str::uuid(),
                ...$s,
            ], $clean, array_keys($clean)),
        ];
    }

    /**
     * What a lesson needs before learners can see it, beyond what a draft needs.
     *
     * @param  array<string, mixed>  $snapshot
     *
     * @throws ValidationException
     */
    public function assertPublishable(array $snapshot): void
    {
        $errors = [];
        if (trim((string) ($snapshot['summary'] ?? '')) === '') {
            $errors['summary'] = [__('Write a summary before publishing.')];
        }
        if (($snapshot['sections'] ?? []) === []) {
            $errors['sections'] = [__('Add at least one section before publishing.')];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Snapshot -> the request shape validate() takes (sections carry `id`, not `uuid`).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function toInput(array $snapshot): array
    {
        return [
            ...$snapshot,
            'sections' => array_map(
                fn (array $s) => ['id' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading'], 'payload' => $s['payload']],
                $snapshot['sections'] ?? [],
            ),
        ];
    }

    /** @return array<string, list<string>> */
    private function checkIds(Lesson $lesson, array $sections): array
    {
        $errors = [];
        $seen = [];
        $ids = array_filter(array_map(fn ($s) => is_string($s['id'] ?? null) ? strtolower($s['id']) : null, $sections));
        if ($ids === []) {
            return [];
        }

        $known = array_flip($this->knownUuids($lesson));
        // A uuid not yet in the database is a new section the client named itself: allowed,
        // as long as no other lesson owns it.
        $foreign = array_flip(LessonSection::query()->whereIn('uuid', $ids)->where('lesson_id', '!=', $lesson->id)->pluck('uuid')->all());

        foreach ($ids as $i => $id) {
            if (isset($seen[$id])) {
                $errors["sections.{$i}.id"] = [__('Each section id can appear only once.')];
            } elseif (isset($foreign[$id]) && ! isset($known[$id])) {
                $errors["sections.{$i}.id"] = [__('This section belongs to another lesson.')];
            }
            $seen[$id] = true;
        }

        return $errors;
    }

    /** @return array<string, list<string>> */
    private function checkMedia(array $sections): array
    {
        $wanted = [];
        foreach ($sections as $i => $section) {
            if ($section['type'] === 'media') {
                $wanted[$i] = $section['payload']['media'];
            }
        }
        if ($wanted === []) {
            return [];
        }

        $existing = array_flip(LessonMedia::query()->whereIn('uuid', array_values($wanted))->pluck('uuid')->all());
        $errors = [];
        foreach ($wanted as $i => $uuid) {
            if (! isset($existing[$uuid])) {
                $errors["sections.{$i}.payload.media"] = [__('This image does not exist. Upload it again.')];
            }
        }

        return $errors;
    }
}

<?php

namespace App\Modules\Content\Services;

use App\Enums\LessonStatus;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Domain;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\Skill;
use App\Modules\Content\Blocks\BlockRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Writes courses from the content files (database/seeders/content/courses/*.php).
 *
 * Rules (ARCHITECTURE D19, docs/PHASE_3_5_CONTENT_AND_ADMIN.md):
 * - Every section is validated by the BlockRegistry before anything is written.
 * - Sections are upserted by a deterministic UUID (lesson slug + section key), never deleted
 *   and recreated, so references to them survive re-imports and reordering.
 * - Once a lesson is edited in the admin console (updated_by set, or a pending draft exists),
 *   the database owns it and imports skip it, unless $force is true.
 * - Lessons removed from a content file are archived, not deleted, and never left live.
 */
final class ContentImporter
{
    /** Namespace for section UUIDs derived from "lesson-slug/section-key". Never change it. */
    private const SECTION_NAMESPACE = '6f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30';

    private const PARKING_OFFSET = 10000;

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly SectionWriter $sections,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $courses  parsed content files
     * @return array{courses: int, lessons_written: int, skipped: list<string>, archived: list<string>}
     *
     * @throws ValidationException when any course, lesson or section is invalid; nothing is written
     */
    public function import(array $courses, bool $force = false): array
    {
        $prepared = $this->prepare($courses);
        $report = ['courses' => count($courses), 'lessons_written' => 0, 'skipped' => [], 'archived' => []];

        DB::transaction(function () use ($prepared, $force, &$report) {
            foreach ($prepared as $course) {
                $this->writeCourse($course, $force, $report);
            }

            $courseIds = Course::query()->pluck('id', 'slug');
            foreach ($prepared as $course) {
                Course::query()->find($courseIds[$course['slug']])
                    ->prerequisites()->sync($courseIds->only($course['prerequisites'])->values());
            }
        });

        return $report;
    }

    /** All lesson slugs defined in the given content files. */
    public static function lessonSlugs(array $courses): array
    {
        $slugs = [];
        foreach ($courses as $course) {
            foreach ($course['chapters'] as $chapter) {
                foreach ($chapter['lessons'] as $lesson) {
                    $slugs[] = $lesson['slug'];
                }
            }
        }

        return $slugs;
    }

    /**
     * Converts the compact section format to {key, uuid, type, heading, payload}:
     * [type, heading, body, extra, data] plus optional named 'key' and 'uuid' elements.
     * 'uuid' pins a section created in the admin console (random UUID) when it is exported
     * back to a file; otherwise the UUID is derived from the lesson slug and section key.
     *
     * @param  array<int|string, mixed>  $s
     * @return array{key: ?string, uuid: mixed, type: string, heading: ?string, payload: array<string, mixed>}
     */
    public static function fromCompact(array $s): array
    {
        [$type, $heading, $body] = [$s[0] ?? null, $s[1] ?? null, $s[2] ?? null];
        $extra = $s[3] ?? null;

        $payload = match ($type) {
            'prose' => ['text' => $body],
            'callout' => ['text' => $body, 'tone' => $extra ?? 'info'],
            'steps' => ['items' => $body],
            'code' => ['code' => $body, 'language' => $extra ?? 'text'],
            'diagram' => ['key' => $body, 'caption' => $extra, 'data' => $s[4] ?? []],
            // New block types are written in their stored shape: [type, heading, payload].
            default => is_array($body) ? $body : [],
        };

        return ['key' => $s['key'] ?? null, 'uuid' => $s['uuid'] ?? null, 'type' => (string) $type, 'heading' => $heading, 'payload' => $payload];
    }

    /**
     * The key of each section: its explicit key, else its heading as a slug, else "type-n",
     * with "-2", "-3" appended to repeats. Section UUIDs derive from these.
     *
     * @param  list<array{key?: ?string, type: string, heading: ?string}>  $sections
     * @return list<string>
     */
    public static function sectionKeys(array $sections): array
    {
        $keys = [];
        $seen = [];
        foreach (array_values($sections) as $i => $section) {
            $key = ($section['key'] ?? null) ?: (($section['heading'] ?? null) ? Str::slug($section['heading']) : "{$section['type']}-".($i + 1));
            $base = $key;
            for ($n = 2; isset($seen[$key]); $n++) {
                $key = "{$base}-{$n}";
            }
            $seen[$key] = true;
            $keys[] = $key;
        }

        return $keys;
    }

    public static function sectionUuid(string $lessonSlug, string $key): string
    {
        return Uuid::uuid5(self::SECTION_NAMESPACE, "{$lessonSlug}/{$key}")->toString();
    }

    /**
     * Validates everything up front and returns courses with normalized sections and their UUIDs.
     *
     * @return list<array<string, mixed>>
     */
    private function prepare(array $courses): array
    {
        $errors = [];
        $domains = Domain::query()->pluck('slug')->all();
        $skills = Skill::query()->pluck('slug')->all();
        // A prerequisite may be another course in this import or one already in the database.
        $courseSlugs = array_merge(array_column($courses, 'slug'), Course::query()->pluck('slug')->all());
        $seen = [];
        $uuids = [];

        foreach ($courses as $ci => $course) {
            $at = "courses.{$course['slug']}";
            in_array($course['domain'], $domains, true) || $errors["{$at}.domain"] = ["Unknown domain {$course['domain']}."];
            in_array($course['difficulty'], [1, 2, 3], true) || $errors["{$at}.difficulty"] = ['Difficulty must be 1, 2 or 3.'];
            foreach (array_diff($course['skills'], $skills) as $s) {
                $errors["{$at}.skills"][] = "Unknown skill {$s}.";
            }
            foreach (array_diff($course['prerequisites'], $courseSlugs) as $p) {
                $errors["{$at}.prerequisites"][] = "Unknown course {$p}.";
            }

            foreach ($course['chapters'] as $chi => $chapter) {
                foreach ($chapter['lessons'] as $li => $lesson) {
                    $slug = $lesson['slug'];
                    if (isset($seen[$slug])) {
                        $errors["lessons.{$slug}"] = ['Duplicate lesson slug.'];
                    }
                    $seen[$slug] = true;

                    $compact = array_map(self::fromCompact(...), $lesson['sections']);
                    try {
                        $clean = $this->blocks->validateSections($compact, "lessons.{$slug}.sections");
                    } catch (ValidationException $e) {
                        $errors += $e->errors();

                        continue;
                    }

                    $keys = self::sectionKeys(array_map(
                        fn ($s, $c) => ['key' => $c['key'], 'type' => $s['type'], 'heading' => $s['heading']],
                        $clean,
                        $compact,
                    ));
                    foreach ($clean as $i => &$section) {
                        $pinned = $compact[$i]['uuid'];
                        if ($pinned !== null && (! is_string($pinned) || ! Uuid::isValid($pinned))) {
                            $errors["lessons.{$slug}.sections.{$i}.uuid"] = ['Must be a UUID.'];
                        }
                        $section['uuid'] = is_string($pinned) ? strtolower($pinned) : self::sectionUuid($slug, $keys[$i]);
                        // A UUID names one section everywhere: upserting by it must never move another lesson's section.
                        if (isset($uuids[$section['uuid']])) {
                            $errors["lessons.{$slug}.sections.{$i}.uuid"] = ["Duplicate section UUID (also in {$uuids[$section['uuid']]})."];
                        }
                        $uuids[$section['uuid']] = $slug;
                    }
                    unset($section);

                    $courses[$ci]['chapters'][$chi]['lessons'][$li]['sections'] = $clean;
                }
            }
        }

        // Pinned UUIDs that already belong to a section of a different lesson.
        $owners = LessonSection::query()->join('lessons', 'lessons.id', '=', 'lesson_sections.lesson_id')
            ->whereIn('lesson_sections.uuid', array_keys($uuids))
            ->pluck('lessons.slug', 'lesson_sections.uuid');
        foreach ($owners as $uuid => $owner) {
            if ($owner !== $uuids[$uuid]) {
                $errors["lessons.{$uuids[$uuid]}.sections"][] = "Section UUID {$uuid} belongs to lesson {$owner}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $courses;
    }

    private function writeCourse(array $data, bool $force, array &$report): void
    {
        $course = Course::query()->updateOrCreate(['slug' => $data['slug']], [
            'domain_id' => Domain::query()->where('slug', $data['domain'])->value('id'),
            'title' => $data['title'],
            'summary' => $data['summary'],
            'description' => $data['description'],
            'difficulty' => $data['difficulty'],
            'objectives' => $data['objectives'],
            'is_published' => true,
            'published_at' => $data['published_at'],
        ]);
        $course->skills()->sync(Skill::query()->whereIn('slug', $data['skills'])->pluck('id'));

        $chapters = [];
        foreach ($data['chapters'] as $i => $chapter) {
            $chapters[$i] = $course->chapters()->updateOrCreate(['position' => $i + 1], ['title' => $chapter['title']]);
        }
        $chapterIds = $course->chapters()->pluck('id');

        $fileSlugs = self::lessonSlugs([$data]);
        $existing = Lesson::query()->whereIn('slug', $fileSlugs)->withExists('draft')->get()->keyBy('slug');
        $isLocked = fn (?Lesson $l) => $l !== null && ! $force && ($l->updated_by !== null || $l->draft_exists);

        // Pass 1: park every lesson this import will write, so pass 2 can renumber freely
        // without tripping the unique (course_chapter_id, position) index.
        $writable = $existing->reject(fn (Lesson $l) => $isLocked($l))->pluck('id');
        Lesson::query()->whereIn('id', $writable)->update(['position' => DB::raw('position + '.self::PARKING_OFFSET)]);

        // Pass 2: write lessons in file order.
        foreach ($data['chapters'] as $ci => $chapterData) {
            $chapter = $chapters[$ci];
            foreach ($chapterData['lessons'] as $li => $l) {
                $current = $existing->get($l['slug']);
                if ($isLocked($current)) {
                    $report['skipped'][] = $l['slug'];

                    continue;
                }

                $lesson = Lesson::query()->updateOrCreate(['slug' => $l['slug']], [
                    'course_chapter_id' => $chapter->id,
                    'position' => $this->freePosition($chapter, $li + 1, $current?->id),
                    'title' => $l['title'],
                    'summary' => $l['summary'],
                    'objectives' => $l['objectives'],
                    'key_takeaways' => $l['takeaways'],
                    'resources' => array_map(fn ($r) => ['label' => $r[0], 'url' => $r[1]], $l['resources']),
                    'estimated_minutes' => $l['minutes'],
                    'status' => LessonStatus::Published,
                    'published_at' => $current?->published_at ?? $data['published_at'],
                ]);
                $this->sections->write($lesson, $l['sections']);
                $report['lessons_written']++;
            }
        }

        // Lessons that left the file: archive seed-owned ones (never delete: progress and roadmaps point at them).
        $removed = Lesson::query()
            ->whereIn('course_chapter_id', $chapterIds)
            ->whereNotIn('slug', $fileSlugs)
            ->where('status', LessonStatus::Published)
            ->whereNull('updated_by')
            ->whereNull('created_by')
            ->get();
        foreach ($removed as $lesson) {
            $lesson->update(['status' => LessonStatus::Archived]);
            $report['archived'][] = $lesson->slug;
        }
    }

    /**
     * The wanted position, unless a lesson this import does not own (admin-edited) holds it;
     * then the end of the chapter.
     */
    private function freePosition(CourseChapter $chapter, int $wanted, ?int $selfId): int
    {
        $taken = Lesson::query()
            ->where('course_chapter_id', $chapter->id)
            ->where('position', $wanted)
            ->when($selfId, fn ($q) => $q->whereKeyNot($selfId))
            ->exists();

        if (! $taken) {
            return $wanted;
        }

        $max = (int) Lesson::query()->where('course_chapter_id', $chapter->id)->where('position', '<', self::PARKING_OFFSET)->max('position');

        return $max + 1;
    }
}

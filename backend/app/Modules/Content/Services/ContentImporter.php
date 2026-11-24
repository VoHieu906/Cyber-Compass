<?php

namespace App\Modules\Content\Services;

use App\Enums\LessonStatus;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\CourseGraph;
use App\Models\Domain;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\LessonSection;
use App\Models\Post;
use App\Models\PostSection;
use App\Models\Skill;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\CourseMap\ArchitectureWriter;
use App\Modules\Content\CourseMap\CourseMap;
use App\Modules\Content\CourseMap\MapSchemas;
use App\Modules\Content\Documents\ReadingTime;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Writes courses and blog posts from the content files (database/seeders/content/courses/*.php,
 * database/seeders/content/posts/*.php). Posts follow the same rules as lessons (D55); images
 * may be named by file (ContentFileMedia, D56).
 *
 * Rules (ARCHITECTURE D19, docs/PHASE_3_5_CONTENT_AND_ADMIN.md):
 * - Every section is validated by the BlockRegistry before anything is written.
 * - Sections are upserted by a deterministic UUID (lesson slug + section key), never deleted
 *   and recreated, so references to them survive re-imports and reordering.
 * - Once a lesson is edited in the admin console (updated_by set, or a pending draft exists),
 *   the database owns it and imports skip it, unless $force is true.
 * - Lessons removed from a content file are archived, not deleted, and never left live.
 * - A course's `graph` and a lesson's `architecture` (F3, D57) are validated with everything
 *   else. The architecture travels with its lesson (skipped with it). A graph an admin saved
 *   (course_graphs.updated_by set) is skipped too unless $force (D58); a graph removed from
 *   the file is removed when the file owns it. Unplaced or unconnected lessons are warnings.
 */
final class ContentImporter
{
    /** Namespace for section UUIDs derived from "lesson-slug/section-key". Never change it. */
    private const SECTION_NAMESPACE = '6f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30';

    /** Namespace for post section UUIDs derived from "post-slug/section-key". Never change it. */
    private const POST_SECTION_NAMESPACE = '0c7e5d3a-9f21-4b6e-a8d4-5e2f1b9c7a13';

    private const PARKING_OFFSET = 10000;

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly SectionWriter $sections,
        private readonly ContentFileMedia $files,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $courses  parsed content files
     * @return array{courses: int, lessons_written: int, skipped: list<string>, archived: list<string>, graphs_skipped: list<string>, warnings: list<string>}
     *
     * @throws ValidationException when any course, lesson or section is invalid; nothing is written
     */
    public function import(array $courses, bool $force = false): array
    {
        $prepared = $this->prepare($courses);
        $report = ['courses' => count($courses), 'lessons_written' => 0, 'skipped' => [], 'archived' => [], 'graphs_skipped' => [], 'warnings' => self::mapWarnings($prepared)];

        DB::transaction(function () use ($prepared, $force, &$report) {
            $this->files->store();
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
     * Converts the compact section format to {key, uuid, type, heading, payload, sources}:
     * [type, heading, body, extra, data] plus optional named 'key', 'uuid' and 'sources' (D68) elements.
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

        return ['key' => $s['key'] ?? null, 'uuid' => $s['uuid'] ?? null, 'type' => (string) $type, 'heading' => $heading, 'payload' => $payload, 'sources' => $s['sources'] ?? null];
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

    public static function postSectionUuid(string $postSlug, string $key): string
    {
        return Uuid::uuid5(self::POST_SECTION_NAMESPACE, "{$postSlug}/{$key}")->toString();
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

            $courseLessons = self::lessonSlugs([$course]);
            if (isset($course['graph'])) {
                [$graphErrors, $courses[$ci]['graph']] = MapSchemas::courseGraph($course['graph'], $courseLessons, "{$at}.graph");
                $errors += $graphErrors;
            }

            foreach ($course['chapters'] as $chi => $chapter) {
                foreach ($chapter['lessons'] as $li => $lesson) {
                    $slug = $lesson['slug'];
                    if (isset($seen[$slug])) {
                        $errors["lessons.{$slug}"] = ['Duplicate lesson slug.'];
                    }
                    $seen[$slug] = true;

                    $clean = $this->prepareSections('lessons', $slug, $lesson['sections'], self::sectionUuid(...), null, $errors, $uuids);
                    if ($clean === null) {
                        continue;
                    }

                    $courses[$ci]['chapters'][$chi]['lessons'][$li]['sections'] = $clean;
                    $courses[$ci]['chapters'][$chi]['lessons'][$li]['architecture'] = $this->prepareArchitecture($lesson, $clean, $courseLessons, $errors);
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

    /**
     * A lesson's architecture from its content file: sub-nodes name sections by key (or UUID),
     * and may link only to other lessons of the same course file.
     *
     * @param  list<array{uuid: string}>  $sections
     * @param  list<string>  $courseLessons
     * @param  array<string, list<string>>  $errors
     * @return array<string, mixed>|null
     */
    private function prepareArchitecture(array $lesson, array $sections, array $courseLessons, array &$errors): ?array
    {
        $raw = $lesson['architecture'] ?? null;
        if ($raw === null) {
            return null;
        }
        $slug = $lesson['slug'];
        $at = "lessons.{$slug}.architecture";
        foreach (is_array($raw) ? ($raw['nodes'] ?? []) : [] as $i => $node) {
            if (is_array($node) && is_string($node['section'] ?? null) && ! Uuid::isValid($node['section'])) {
                $raw['nodes'][$i]['section'] = self::sectionUuid($slug, $node['section']);
            }
            $link = is_array($node) ? ($node['lesson'] ?? null) : null;
            if (is_string($link) && ($link === $slug || ! in_array($link, $courseLessons, true))) {
                $errors["{$at}.nodes.{$i}.lesson"][] = "Link to another lesson of this course, not {$link}.";
            }
        }
        [$archErrors, $clean] = MapSchemas::architecture($raw, array_column($sections, 'uuid'), $at);
        $errors += $archErrors;

        return $clean;
    }

    /**
     * Unplaced, unconnected and problem-less lessons of every course with a map: authors should
     * look, but nothing is refused.
     *
     * @return list<string>
     */
    private static function mapWarnings(array $courses): array
    {
        $warnings = [];
        foreach ($courses as $course) {
            if (! isset($course['graph'])) {
                continue;
            }
            $lessons = [];
            foreach ($course['chapters'] as $chapter) {
                foreach ($chapter['lessons'] as $lesson) {
                    $lessons[$lesson['slug']] = $lesson['architecture'] ?? null;
                }
            }
            foreach (CourseMap::findIssues($course['graph'], array_keys($lessons), $lessons) as $issue) {
                $warnings[] = "{$course['slug']}: {$issue['type']} {$issue['lesson']}";
            }
        }

        return $warnings;
    }

    /**
     * Validates one document's sections (compact format) and gives each its UUID: pinned, or
     * derived from the slug and section key. Image references become media UUIDs (D56).
     * Returns null when the blocks are invalid (errors are added).
     *
     * @param  Closure(string, string): string  $uuidFor  slug, key => uuid
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string>  $uuids  uuid => owner slug, across the whole import
     * @return list<array{uuid: string, type: string, heading: ?string, payload: array}>|null
     */
    private function prepareSections(string $owner, string $slug, array $raw, Closure $uuidFor, ?int $max, array &$errors, array &$uuids): ?array
    {
        $compact = array_map(self::fromCompact(...), array_values($raw));
        foreach ($compact as $i => $c) {
            if ($c['type'] === 'media' && ContentFileMedia::isReference($c['payload']['media'] ?? null)) {
                $compact[$i]['payload']['media'] = $this->files->resolve($c['payload']['media'], "{$owner}.{$slug}.sections.{$i}.payload.media", $errors) ?? '';
            }
        }
        if ($max !== null && count($compact) > $max) {
            $errors["{$owner}.{$slug}.sections"][] = "At most {$max} sections.";
        }

        try {
            $clean = $this->blocks->validateSections($compact, "{$owner}.{$slug}.sections");
        } catch (ValidationException $e) {
            $errors += $e->errors();

            return null;
        }

        $keys = self::sectionKeys(array_map(
            fn ($s, $c) => ['key' => $c['key'], 'type' => $s['type'], 'heading' => $s['heading']],
            $clean,
            $compact,
        ));
        foreach ($clean as $i => &$section) {
            $pinned = $compact[$i]['uuid'];
            if ($pinned !== null && (! is_string($pinned) || ! Uuid::isValid($pinned))) {
                $errors["{$owner}.{$slug}.sections.{$i}.uuid"] = ['Must be a UUID.'];
            }
            $section['uuid'] = is_string($pinned) ? strtolower($pinned) : $uuidFor($slug, $keys[$i]);
            // A UUID names one section everywhere: upserting by it must never move another document's section.
            if (isset($uuids[$section['uuid']])) {
                $errors["{$owner}.{$slug}.sections.{$i}.uuid"] = ["Duplicate section UUID (also in {$uuids[$section['uuid']]})."];
            }
            $uuids[$section['uuid']] = $slug;
        }
        unset($section);

        return $clean;
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
                ArchitectureWriter::write($lesson, $l['architecture'] ?? null);
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

        $this->writeGraph($course, $data['graph'] ?? null, $force, $report);
    }

    /** The file's map, unless an admin saved this course's map since (D58). */
    private function writeGraph(Course $course, ?array $graph, bool $force, array &$report): void
    {
        $current = CourseGraph::query()->where('course_id', $course->id)->first();
        if ($current?->updated_by !== null && ! $force) {
            $report['graphs_skipped'][] = $course->slug;

            return;
        }
        if ($graph === null) {
            $current?->delete();

            return;
        }
        if ($current?->graph == $graph) {
            return;
        }
        CourseGraph::query()->updateOrCreate(['course_id' => $course->id], [
            'graph' => $graph,
            'lock_version' => ($current->lock_version ?? 0) + 1,
            'updated_by' => null,
        ]);
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

    /**
     * Imports blog posts from database/seeders/content/posts/*.php (F2, D55) with the lesson
     * rules: everything validated before anything is written, posts edited in the admin skipped
     * unless $force, section UUIDs from post slug + section key, and seed-owned posts that left
     * the files archived, never deleted.
     *
     * @param  list<array<string, mixed>>  $posts  parsed content files
     * @return array{posts: int, posts_written: int, skipped: list<string>, archived: list<string>}
     *
     * @throws ValidationException when any post or section is invalid; nothing is written
     */
    public function importPosts(array $posts, bool $force = false): array
    {
        $prepared = $this->preparePosts($posts);
        $report = ['posts' => count($posts), 'posts_written' => 0, 'skipped' => [], 'archived' => []];

        DB::transaction(function () use ($prepared, $force, &$report) {
            $this->files->store();
            $slugs = array_column($prepared, 'slug');
            $existing = Post::query()->whereIn('slug', $slugs)->withExists('draft')->get()->keyBy('slug');

            foreach ($prepared as $data) {
                $current = $existing->get($data['slug']);
                if ($current && ! $force && ($current->updated_by !== null || $current->draft_exists)) {
                    $report['skipped'][] = $data['slug'];

                    continue;
                }

                $post = Post::query()->updateOrCreate(['slug' => $data['slug']], [
                    'title' => $data['title'],
                    'summary' => $data['summary'],
                    'cover' => $data['cover'],
                    'tags' => $data['tags'],
                    'estimated_minutes' => ReadingTime::minutes($data['sections']),
                    'status' => LessonStatus::Published,
                    'published_at' => $current?->published_at ?? $data['published_at'],
                ]);
                $this->sections->write($post, $data['sections']);
                $report['posts_written']++;
            }

            $removed = Post::query()
                ->whereNotIn('slug', $slugs)
                ->where('status', LessonStatus::Published)
                ->whereNull('updated_by')
                ->whereNull('created_by')
                ->get();
            foreach ($removed as $post) {
                $post->update(['status' => LessonStatus::Archived]);
                $report['archived'][] = $post->slug;
            }
        });

        return $report;
    }

    /** @return list<array<string, mixed>> */
    private function preparePosts(array $posts): array
    {
        $errors = [];
        $seen = [];
        $uuids = [];
        $covers = [];

        foreach ($posts as $pi => $post) {
            $slug = is_string($post['slug'] ?? null) ? $post['slug'] : "#{$pi}";
            $at = "posts.{$slug}";
            preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) && strlen($slug) <= 120 || $errors["{$at}.slug"][] = 'Use lowercase letters, numbers and single hyphens (at most 120).';
            isset($seen[$slug]) && $errors["{$at}.slug"][] = 'Duplicate post slug.';
            $seen[$slug] = true;
            $title = trim((string) ($post['title'] ?? ''));
            ($title !== '' && mb_strlen($title) <= 160) || $errors["{$at}.title"][] = 'A title of at most 160 characters is required.';
            $summary = trim((string) ($post['summary'] ?? ''));
            ($summary !== '' && mb_strlen($summary) <= 300) || $errors["{$at}.summary"][] = 'A summary of at most 300 characters is required.';
            $tags = $post['tags'] ?? [];
            $tagsOk = is_array($tags) && count($tags) <= 8 && count($tags) === count(array_unique($tags));
            foreach ($tagsOk ? $tags : [] as $tag) {
                $tagsOk = $tagsOk && is_string($tag) && strlen($tag) <= 30 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $tag);
            }
            $tagsOk || $errors["{$at}.tags"][] = 'At most 8 distinct tags, each lowercase letters, numbers and hyphens (at most 30).';
            strtotime((string) ($post['published_at'] ?? '')) !== false || $errors["{$at}.published_at"][] = 'A publication date is required.';

            $cover = $post['cover'] ?? null;
            if (ContentFileMedia::isReference($cover)) {
                $cover = $this->files->resolve($cover, "{$at}.cover", $errors);
            } elseif ($cover !== null) {
                is_string($cover) && Uuid::isValid($cover) ? $covers["{$at}.cover"] = strtolower($cover) : $errors["{$at}.cover"][] = 'A media UUID or an image file reference.';
            }

            $sections = $this->prepareSections('posts', $slug, $post['sections'] ?? [], self::postSectionUuid(...), Post::MAX_SECTIONS, $errors, $uuids);

            $posts[$pi] = [
                'slug' => $slug,
                'title' => $title,
                'summary' => $summary,
                'tags' => is_array($tags) ? array_values($tags) : [],
                'published_at' => $post['published_at'] ?? null,
                'cover' => is_string($cover) ? strtolower($cover) : null,
                'sections' => $sections ?? [],
            ];
        }

        // Uploaded covers named by UUID must exist (file references are created on write).
        $known = LessonMedia::query()->whereIn('uuid', array_values($covers))->pluck('uuid')->flip();
        foreach ($covers as $key => $uuid) {
            isset($known[$uuid]) || $errors[$key][] = "Unknown media {$uuid}.";
        }

        // Pinned UUIDs that already belong to a section of a different post.
        $owners = PostSection::query()->join('posts', 'posts.id', '=', 'post_sections.post_id')
            ->whereIn('post_sections.uuid', array_keys($uuids))
            ->pluck('posts.slug', 'post_sections.uuid');
        foreach ($owners as $uuid => $owner) {
            if ($owner !== $uuids[$uuid]) {
                $errors["posts.{$uuids[$uuid]}.sections"][] = "Section UUID {$uuid} belongs to post {$owner}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $posts;
    }
}

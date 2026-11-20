<?php

namespace App\Modules\Content\Services;

use App\Enums\LessonStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonSection;

/**
 * Writes a course's live content back to the content-file format the importer reads
 * (database/seeders/content/courses/<slug>.php), so lessons curated in the admin console can
 * be kept in the repository (ARCHITECTURE D19, PHASE_3_5 M4).
 *
 * - Only published lessons are exported, in chapter and lesson order. Drafts are not: the
 *   file holds what learners see. Archived and never-published lessons are left out.
 * - Every chapter is kept, even one with no published lesson, because the importer matches
 *   chapters by position.
 * - The five original block types use the compact tuple form; the rest use the stored shape
 *   [type, heading, payload]. Both are what ContentImporter::fromCompact reads.
 * - A section whose UUID is not the one the importer would derive (made in the admin console,
 *   or renamed since) carries 'uuid' explicitly, so re-importing never changes a section's UUID.
 */
final class ContentExporter
{
    /** @return array{php: string, lessons: int, skipped_drafts: list<string>, empty_chapters: list<string>} */
    public function export(Course $course): array
    {
        $course->loadMissing(['domain', 'skills', 'prerequisites', 'chapters' => fn ($q) => $q->orderBy('position')]);
        $chapters = [];
        $lessons = 0;
        $drafts = [];
        $empty = [];

        foreach ($course->chapters as $chapter) {
            $published = Lesson::query()
                ->where('course_chapter_id', $chapter->id)
                ->where('status', LessonStatus::Published)
                ->withExists('draft')
                ->with('sections')
                ->orderBy('position')
                ->get();
            if ($published->isEmpty()) {
                // Kept (with no lessons): the importer matches chapters by position.
                $empty[] = $chapter->title;
            }
            $chapters[] = [
                'title' => $chapter->title,
                'lessons' => $published->map(function (Lesson $lesson) use (&$drafts) {
                    if ($lesson->draft_exists) {
                        $drafts[] = $lesson->slug;
                    }

                    return $this->lesson($lesson);
                })->all(),
            ];
            $lessons += $published->count();
        }

        $data = [
            'slug' => $course->slug,
            'domain' => $course->domain->slug,
            'title' => $course->title,
            'summary' => $course->summary,
            'description' => $course->description,
            'difficulty' => $course->difficulty->value,
            'published_at' => $course->published_at?->toDateString(),
            'objectives' => $course->objectives ?? [],
            'skills' => $course->skills->pluck('slug')->sort()->values()->all(),
            'prerequisites' => $course->prerequisites->pluck('slug')->sort()->values()->all(),
            'chapters' => $chapters,
        ];

        $php = "<?php\n\n// Exported from the database by `php artisan content:export {$course->slug}`.\n"
            ."// Edit freely: `php artisan content:import` reads this file back.\n\nreturn "
            .PhpArrayWriter::write($data).";\n";

        return ['php' => $php, 'lessons' => $lessons, 'skipped_drafts' => $drafts, 'empty_chapters' => $empty];
    }

    /** @return array<string, mixed> */
    private function lesson(Lesson $lesson): array
    {
        $sections = $lesson->sections->values();
        $keys = ContentImporter::sectionKeys($sections->map(fn (LessonSection $s) => [
            'type' => $s->type,
            'heading' => $s->heading,
        ])->all());

        return [
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'minutes' => $lesson->estimated_minutes,
            'summary' => $lesson->summary,
            'objectives' => $lesson->objectives ?? [],
            'takeaways' => $lesson->key_takeaways ?? [],
            'resources' => array_map(fn ($r) => [$r['label'], $r['url']], $lesson->resources ?? []),
            'sections' => $sections->map(function (LessonSection $s, int $i) use ($lesson, $keys) {
                $tuple = self::compact($s->type, $s->heading, $s->payload);
                if ($s->uuid !== ContentImporter::sectionUuid($lesson->slug, $keys[$i])) {
                    $tuple['uuid'] = $s->uuid;
                }

                return $tuple;
            })->all(),
        ];
    }

    /**
     * The inverse of ContentImporter::fromCompact.
     *
     * @param  array<string, mixed>  $p
     * @return array<int|string, mixed>
     */
    public static function compact(string $type, ?string $heading, array $p): array
    {
        return match ($type) {
            'prose' => ['prose', $heading, $p['text']],
            'callout' => ['callout', $heading, $p['text'], $p['tone']],
            'steps' => ['steps', $heading, $p['items']],
            'code' => ['code', $heading, $p['code'], $p['language']],
            'diagram' => ['diagram', $heading, $p['key'], $p['caption'] ?? null, $p['data']],
            default => [$type, $heading, $p],
        };
    }
}

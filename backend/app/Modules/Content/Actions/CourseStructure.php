<?php

namespace App\Modules\Content\Actions;

use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Content\Services\SectionWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chapters within a course and the order of lessons within a chapter. Structural changes
 * apply immediately (they are not drafted), and each is audited. Courses themselves are
 * still created by the seeder in this phase (assumption A2).
 */
final class CourseStructure
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function createChapter(User $actor, Course $course, string $title): CourseChapter
    {
        return DB::transaction(function () use ($actor, $course, $title) {
            $chapter = $course->chapters()->create([
                'title' => $title,
                'position' => (int) $course->chapters()->lockForUpdate()->max('position') + 1,
            ]);
            $this->audit->record($actor, 'chapter.created', 'chapter', $chapter->id, ['title' => $title, 'course_id' => $course->id]);

            return $chapter;
        });
    }

    public function renameChapter(User $actor, CourseChapter $chapter, string $title): CourseChapter
    {
        if ($chapter->title === $title) {
            return $chapter;
        }

        return DB::transaction(function () use ($actor, $chapter, $title) {
            $from = $chapter->title;
            $chapter->update(['title' => $title]);
            $this->audit->record($actor, 'chapter.renamed', 'chapter', $chapter->id, ['title' => [$from, $title]]);

            return $chapter;
        });
    }

    /** @param  list<int>  $chapterIds  every chapter of the course, in the new order */
    public function reorderChapters(User $actor, Course $course, array $chapterIds): void
    {
        DB::transaction(function () use ($actor, $course, $chapterIds) {
            $current = $course->chapters()->lockForUpdate()->orderBy('position')->pluck('id')->all();
            self::assertSameSet($current, $chapterIds, 'chapter_ids', __('Send every chapter of this course exactly once.'));

            self::renumber(CourseChapter::query(), $chapterIds);
            $this->audit->record($actor, 'chapters.reordered', 'course', $course->id, ['chapter_ids' => [$current, $chapterIds]]);
        });
    }

    /**
     * @param  list<int>  $lessonIds  every lesson of the chapter (any status), in the new order
     *
     * The reordered lessons become admin-owned (updated_by), so a later content import keeps
     * this order instead of putting the file's order back (ARCHITECTURE D19).
     */
    public function reorderLessons(User $actor, CourseChapter $chapter, array $lessonIds): void
    {
        DB::transaction(function () use ($actor, $chapter, $lessonIds) {
            $current = Lesson::query()->where('course_chapter_id', $chapter->id)->lockForUpdate()->orderBy('position')->pluck('id')->all();
            self::assertSameSet($current, $lessonIds, 'lesson_ids', __('Send every lesson of this chapter exactly once.'));

            self::renumber(Lesson::query(), $lessonIds);
            Lesson::query()->whereIn('id', $lessonIds)->update(['updated_by' => $actor->id]);
            $this->audit->record($actor, 'lessons.reordered', 'chapter', $chapter->id, ['lesson_ids' => [$current, $lessonIds]]);
        });
    }

    /** @param  list<int>  $ids */
    private static function renumber($query, array $ids): void
    {
        // Two passes, so the unique position index never sees a duplicate mid-way.
        (clone $query)->whereIn('id', $ids)->update(['position' => DB::raw('position + '.SectionWriter::PARKING_OFFSET)]);
        foreach (array_values($ids) as $i => $id) {
            (clone $query)->whereKey($id)->update(['position' => $i + 1]);
        }
    }

    /**
     * @param  list<int>  $current
     * @param  list<int>  $given
     */
    private static function assertSameSet(array $current, array $given, string $field, string $message): void
    {
        $given = array_map('intval', $given);
        $a = $current;
        $b = $given;
        sort($a);
        sort($b);
        if (count($given) !== count(array_unique($given)) || $a !== $b) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}

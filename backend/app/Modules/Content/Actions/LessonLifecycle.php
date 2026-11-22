<?php

namespace App\Modules\Content\Actions;

use App\Enums\LessonStatus;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Models\LessonRevision;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Content\Documents\DocumentDrafts;
use App\Modules\Content\Documents\DocumentPublisher;
use App\Modules\Content\Services\LessonWorkingCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The small lesson actions: create, discard draft, archive, delete, restore a revision.
 * Each is one transaction with its audit entry. Publishing and saving have their own classes.
 */
final class LessonLifecycle
{
    public function __construct(
        private readonly LessonWorkingCopy $copy,
        private readonly AuditLogger $audit,
        private readonly DocumentPublisher $publisher,
    ) {}

    /** A new lesson: an invisible `draft` row at the end of the chapter, plus its draft. */
    public function create(User $actor, CourseChapter $chapter, string $title, string $slug): Lesson
    {
        return DB::transaction(function () use ($actor, $chapter, $title, $slug) {
            $lesson = Lesson::query()->create([
                'course_chapter_id' => $chapter->id,
                'slug' => $slug,
                'title' => $title,
                'summary' => '',
                'objectives' => [],
                'key_takeaways' => [],
                'resources' => [],
                'estimated_minutes' => 10,
                'position' => (int) Lesson::query()->where('course_chapter_id', $chapter->id)->max('position') + 1,
                'status' => LessonStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            DocumentDrafts::store($lesson, null, $this->copy->fromLive($lesson), $actor);
            $this->audit->record($actor, 'lesson.created', 'lesson', $lesson->id, ['slug' => $slug, 'chapter_id' => $chapter->id]);

            return $lesson;
        });
    }

    /** Drops unpublished changes. A never-published lesson has nothing to fall back to: delete it instead. */
    public function discardDraft(User $actor, Lesson $lesson): void
    {
        if ($lesson->published_at === null) {
            throw ValidationException::withMessages(['lesson' => __('This lesson was never published, so there is nothing to go back to. Delete the lesson instead.')]);
        }

        $this->publisher->discardDraft($actor, $lesson);
    }

    /** Hides a published lesson from learners. Progress and roadmap links are kept; publishing brings it back. */
    public function archive(User $actor, Lesson $lesson): void
    {
        if ($lesson->status !== LessonStatus::Published) {
            throw ValidationException::withMessages(['lesson' => __('Only a published lesson can be archived.')]);
        }

        DB::transaction(function () use ($actor, $lesson) {
            $lesson->update(['status' => LessonStatus::Archived, 'updated_by' => $actor->id]);
            $this->audit->record($actor, 'lesson.archived', 'lesson', $lesson->id, ['status' => ['published', 'archived']]);
        });
    }

    /**
     * Deletes a lesson that never reached learners. Anything learners or roadmaps may point
     * at is archived instead (409), so history is never lost.
     */
    public function delete(User $actor, Lesson $lesson): void
    {
        if (! $this->canDelete($lesson)) {
            throw new ConflictHttpException(__('This lesson has been published or is linked to progress or a roadmap topic. Archive it instead.'));
        }

        DB::transaction(function () use ($actor, $lesson) {
            $this->audit->record($actor, 'lesson.deleted', 'lesson', $lesson->id, ['slug' => $lesson->slug, 'title' => $lesson->title]);
            $lesson->delete();
        });
    }

    public function canDelete(Lesson $lesson): bool
    {
        return $lesson->published_at === null
            && ! $lesson->progress()->exists()
            && ! $lesson->topics()->exists();
    }

    /** Copies a revision into the draft. Learners see nothing until it is published. */
    public function restore(User $actor, Lesson $lesson, LessonRevision $revision, int $lockVersion): void
    {
        $this->publisher->restore($actor, $lesson, $this->copy, $revision, $lockVersion);
    }
}

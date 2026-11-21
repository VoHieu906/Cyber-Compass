<?php

namespace App\Modules\Content\Actions;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\LessonRevision;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Content\Events\LessonPublished;
use App\Modules\Content\Services\LessonWorkingCopy;
use App\Modules\Content\Services\SectionWriter;
use Illuminate\Support\Facades\DB;

/**
 * Makes the working copy live in one transaction: lesson row, sections upserted by UUID,
 * revision n+1, draft dropped. Also how an archived lesson comes back.
 * The draft is validated again, so a schema tightened since it was saved still applies.
 */
final class PublishLesson
{
    public function __construct(
        private readonly LessonWorkingCopy $copy,
        private readonly SectionWriter $sections,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Lesson $lesson, int $lockVersion, ?string $note): LessonRevision
    {
        return DB::transaction(function () use ($actor, $lesson, $lockVersion, $note) {
            $draft = SaveDraft::lockedDraft($lesson);
            SaveDraft::assertVersion($draft, $lockVersion);

            $snapshot = $this->copy->validate($lesson, LessonWorkingCopy::toInput($this->copy->current($lesson)));
            $this->copy->assertPublishable($snapshot);

            $moved = $snapshot['chapter_id'] !== $lesson->course_chapter_id;
            $firstPublish = $lesson->published_at === null;
            $lesson->fill([
                'course_chapter_id' => $snapshot['chapter_id'],
                'slug' => $snapshot['slug'],
                'title' => $snapshot['title'],
                'summary' => $snapshot['summary'],
                'estimated_minutes' => $snapshot['estimated_minutes'],
                'objectives' => $snapshot['objectives'],
                'key_takeaways' => $snapshot['key_takeaways'],
                'resources' => $snapshot['resources'],
                'status' => LessonStatus::Published,
                'published_at' => $lesson->published_at ?? now(),
                'updated_by' => $actor->id,
            ]);
            if ($moved) {
                // Joins its new chapter at the end; reorder from the course page.
                $lesson->position = (int) Lesson::query()->where('course_chapter_id', $snapshot['chapter_id'])->max('position') + 1;
            }
            $lesson->save();

            $this->sections->write($lesson, $snapshot['sections']);

            $version = (int) LessonRevision::query()->where('lesson_id', $lesson->id)->lockForUpdate()->max('version') + 1;
            $revision = LessonRevision::query()->create([
                'lesson_id' => $lesson->id,
                'version' => $version,
                'snapshot' => $snapshot,
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            $draft?->delete();
            $lesson->setRelation('draft', null);

            $this->audit->record($actor, 'lesson.published', 'lesson', $lesson->id, array_filter([
                'version' => $version,
                'note' => $note,
            ], fn ($v) => $v !== null));

            if ($firstPublish) {
                LessonPublished::dispatch($lesson);
            }

            return $revision;
        });
    }
}

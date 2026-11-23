<?php

namespace App\Modules\Content\Actions;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\LessonRevision;
use App\Models\User;
use App\Modules\Content\CourseMap\ArchitectureWriter;
use App\Modules\Content\Documents\DocumentPublisher;
use App\Modules\Content\Events\LessonPublished;
use App\Modules\Content\Services\LessonWorkingCopy;

/**
 * Makes the working copy live in one transaction (the shared DocumentPublisher, D55): lesson
 * row, its architecture (F3), sections upserted by UUID, revision n+1, draft dropped. Also how an archived lesson
 * comes back. The first publish raises LessonPublished.
 */
final class PublishLesson
{
    public function __construct(
        private readonly LessonWorkingCopy $copy,
        private readonly DocumentPublisher $publisher,
    ) {}

    public function handle(User $actor, Lesson $lesson, int $lockVersion, ?string $note): LessonRevision
    {
        $firstPublish = $lesson->published_at === null;

        /** @var LessonRevision */
        return $this->publisher->publish($actor, $lesson, $this->copy, $lockVersion, $note, function (array $snapshot) use ($actor, $lesson) {
            $moved = $snapshot['chapter_id'] !== $lesson->course_chapter_id;
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
            ArchitectureWriter::write($lesson, $snapshot['architecture'] ?? null);
        }, function () use ($lesson, $firstPublish) {
            if ($firstPublish) {
                LessonPublished::dispatch($lesson);
            }
        });
    }
}

<?php

namespace App\Modules\Content\Services;

use App\Models\Lesson;
use App\Models\LessonSection;
use App\Modules\Learning\Services\ExerciseReferences;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Writes a lesson's live sections from a validated, ordered list. Sections are upserted
 * by UUID, never deleted and recreated, so references to them (exercise questions)
 * survive reordering (ARCHITECTURE D20). A section an active exercise question points at is
 * never deleted: the write is refused with 409 (D30). Used by the content importer and by
 * publishing. Call inside a transaction.
 */
final class SectionWriter
{
    /** Positions are moved out of the way first, so renumbering can't trip unique (lesson_id, position). */
    public const PARKING_OFFSET = 10000;

    public function __construct(private readonly ExerciseReferences $references) {}

    /** @param  list<array{uuid: string, type: string, heading: ?string, payload: array}>  $sections */
    public function write(Lesson $lesson, array $sections): void
    {
        $this->refuseRemovingReferenced($lesson, array_column($sections, 'uuid'));

        LessonSection::query()->where('lesson_id', $lesson->id)
            ->update(['position' => DB::raw('position + '.self::PARKING_OFFSET)]);

        foreach (array_values($sections) as $i => $section) {
            LessonSection::query()->updateOrCreate(['uuid' => $section['uuid']], [
                'lesson_id' => $lesson->id,
                'position' => $i + 1,
                'type' => $section['type'],
                'heading' => $section['heading'],
                'payload' => $section['payload'],
            ]);
        }

        LessonSection::query()->where('lesson_id', $lesson->id)
            ->whereNotIn('uuid', array_column($sections, 'uuid'))
            ->delete();
    }

    /** @param  list<string>  $keep */
    private function refuseRemovingReferenced(Lesson $lesson, array $keep): void
    {
        if (! $lesson->exists) {
            return;
        }
        $referenced = array_diff_key($this->references->forLesson($lesson->id), array_flip($keep));
        if ($referenced === []) {
            return;
        }
        $headings = LessonSection::query()->whereIn('uuid', array_keys($referenced))->pluck('heading', 'uuid');
        $list = collect($referenced)->map(fn (array $questions, string $uuid) => '"'.($headings[$uuid] ?? $uuid).'" ('.implode(', ', $questions).')')->implode('; ');

        throw new ConflictHttpException(__('These sections teach exercise questions and cannot be removed: :sections. Change the exercise first.', ['sections' => $list]));
    }
}

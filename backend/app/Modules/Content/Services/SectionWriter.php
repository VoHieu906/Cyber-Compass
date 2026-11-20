<?php

namespace App\Modules\Content\Services;

use App\Models\Lesson;
use App\Models\LessonSection;
use Illuminate\Support\Facades\DB;

/**
 * Writes a lesson's live sections from a validated, ordered list. Sections are upserted
 * by UUID, never deleted and recreated, so references to them (Phase 5 exercises)
 * survive reordering (ARCHITECTURE D20). Used by the content importer and by publishing.
 * Call inside a transaction.
 */
final class SectionWriter
{
    /** Positions are moved out of the way first, so renumbering can't trip unique (lesson_id, position). */
    public const PARKING_OFFSET = 10000;

    /** @param  list<array{uuid: string, type: string, heading: ?string, payload: array}>  $sections */
    public function write(Lesson $lesson, array $sections): void
    {
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
}

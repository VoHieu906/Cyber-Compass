<?php

namespace App\Modules\Content\CourseMap;

use App\Models\Lesson;
use App\Models\LessonArchitecture;

/** Writes a lesson's live architecture (publishing and the importer, F3). Null removes it. */
final class ArchitectureWriter
{
    /** @param  array<string, mixed>|null  $architecture  already validated (MapSchemas) */
    public static function write(Lesson $lesson, ?array $architecture): void
    {
        if ($architecture === null) {
            LessonArchitecture::query()->where('lesson_id', $lesson->id)->delete();
        } else {
            LessonArchitecture::query()->updateOrCreate(['lesson_id' => $lesson->id], ['architecture' => $architecture]);
        }
        $lesson->unsetRelation('architecture');
    }
}

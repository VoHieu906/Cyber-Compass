<?php

namespace App\Modules\Learning\Services;

use App\Models\ExerciseQuestion;

/**
 * Which lesson sections active exercise questions point at (ARCHITECTURE D30). Read by the
 * section writer, which refuses to delete them, and by the lesson editor, which warns first.
 */
final class ExerciseReferences
{
    /** @return array<string, list<string>> section UUID => question labels ("lesson-slug/key") */
    public function forLesson(int $lessonId): array
    {
        return ExerciseQuestion::query()
            ->where('is_archived', false)
            ->whereHas('exercise', fn ($q) => $q->where('lesson_id', $lessonId))
            ->whereNotNull('lesson_section_id')
            ->with(['section:id,uuid', 'exercise.lesson:id,slug'])
            ->orderBy('position')
            ->get()
            ->groupBy(fn (ExerciseQuestion $q) => $q->section->uuid)
            ->map(fn ($questions) => $questions->map(fn (ExerciseQuestion $q) => $q->exercise->lesson->slug.'/'.$q->key)->values()->all())
            ->all();
    }
}

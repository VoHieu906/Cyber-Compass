<?php

namespace App\Modules\Learning\Services;

use App\Models\Exercise;
use App\Models\ExerciseAttemptAnswer;
use App\Models\ExerciseQuestion;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Modules\Content\Services\ContentImporter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads lesson exercises from seeders/content/exercises/*.php (ARCHITECTURE D29).
 *
 * Same rules as the other loaders: every file is validated before anything is written,
 * questions are matched by their stable key within a lesson, and a question that leaves the
 * file is archived, never deleted, because attempts refer to it. Each question names the
 * lesson section that teaches it, by section key (the seeded UUID is derived from it) or by
 * an explicit `section_uuid`; a reference to a section that does not exist fails the load.
 *
 * File shape: ['lesson-slug' => ['pass_percent' => 70, 'questions' => ['key' => [...]]]].
 */
final class ExerciseLoader
{
    public function __construct(private readonly QuestionTypes $types) {}

    /**
     * @param  array<string, array<string, mixed>>  $lessons  every exercise file, merged
     * @return array{exercises: int, questions: int, archived: list<string>}
     *
     * @throws InvalidArgumentException listing every problem
     */
    public function load(array $lessons): array
    {
        $models = Lesson::query()->whereIn('slug', array_keys($lessons))->get(['id', 'slug'])->keyBy('slug');
        [$errors, $sections] = $this->validate($lessons, $models->map(fn (Lesson $l) => $l->id)->all());
        if ($errors) {
            throw new InvalidArgumentException("The exercise files are invalid:\n- ".implode("\n- ", $errors));
        }

        return DB::transaction(function () use ($lessons, $models, $sections) {
            $count = 0;
            $archived = [];
            $loaded = [];
            foreach ($lessons as $slug => $data) {
                $exercise = Exercise::query()->updateOrCreate(['lesson_id' => $models[$slug]->id], ['pass_percent' => $data['pass_percent'] ?? 70]);
                $loaded[] = $exercise->id;
                $position = 0;
                foreach ($data['questions'] as $key => $q) {
                    $compiled = $this->types->compile("{$slug}/{$key}", $q);
                    $existing = ExerciseQuestion::query()->where('exercise_id', $exercise->id)->where('key', $key)->first();
                    if ($existing && $existing->type->value !== $q['type']
                        && ExerciseAttemptAnswer::query()->where('exercise_question_id', $existing->id)->exists()) {
                        throw new InvalidArgumentException("Exercise question {$slug}/{$key} has been answered, so its type cannot change. Give the changed question a new key.");
                    }
                    ExerciseQuestion::query()->updateOrCreate(['exercise_id' => $exercise->id, 'key' => $key], [
                        'position' => ++$position,
                        'type' => $q['type'],
                        'prompt' => $q['prompt'],
                        'payload' => $compiled['payload'],
                        'answer' => $compiled['answer'],
                        'explanation' => $q['explanation'],
                        'lesson_section_id' => $sections["{$slug}/{$key}"],
                        'is_archived' => false,
                    ]);
                    $count++;
                }
                $archived = [...$archived, ...$this->archive(ExerciseQuestion::query()->where('exercise_id', $exercise->id)->whereNotIn('key', array_keys($data['questions'])), $slug)];
            }
            // Exercises whose lesson left the files keep their history; their questions are archived.
            $archived = [...$archived, ...$this->archive(ExerciseQuestion::query()->whereNotIn('exercise_id', $loaded), null)];

            return ['exercises' => count($loaded), 'questions' => $count, 'archived' => $archived];
        });
    }

    /** @return list<string> */
    private function archive($query, ?string $slug): array
    {
        $rows = $query->where('is_archived', false)->with('exercise.lesson:id,slug')->get();
        ExerciseQuestion::query()->whereIn('id', $rows->pluck('id'))->update(['is_archived' => true]);

        return $rows->map(fn (ExerciseQuestion $q) => ($slug ?? $q->exercise->lesson->slug).'/'.$q->key)->all();
    }

    /**
     * @param  array<string, mixed>  $lessons
     * @param  array<string, int>  $lessonIds  by slug
     * @return array{0: list<string>, 1: array<string, int>} errors, and the section id of each question
     */
    private function validate(array $lessons, array $lessonIds): array
    {
        $errors = [];
        $refs = [];
        foreach ($lessons as $slug => $data) {
            if (! isset($lessonIds[$slug])) {
                $errors[] = "{$slug}: no lesson with this slug";

                continue;
            }
            $pass = $data['pass_percent'] ?? 70;
            if (! is_int($pass) || $pass < 1 || $pass > 100) {
                $errors[] = "{$slug}: pass_percent must be 1 to 100";
            }
            $questions = $data['questions'] ?? null;
            if (! is_array($questions) || $questions === [] || array_is_list($questions)) {
                $errors[] = "{$slug}: questions must be a non-empty list keyed by question key";

                continue;
            }
            foreach ($questions as $key => $q) {
                $at = "{$slug}/{$key}";
                if (! is_string($key) || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) || strlen($key) > 120) {
                    $errors[] = "{$at}: the key must be lowercase words joined by hyphens (at most 120 characters)";
                }
                if (! is_array($q)) {
                    $errors[] = "{$at}: must be an array";

                    continue;
                }
                foreach (['prompt', 'explanation'] as $field) {
                    if (! is_string($q[$field] ?? null) || trim($q[$field]) === '') {
                        $errors[] = "{$at}: {$field} is required";
                    }
                }
                foreach ($this->types->validate($q) as $message) {
                    $errors[] = "{$at}: {$message}";
                }

                $uuid = match (true) {
                    is_string($q['section_uuid'] ?? null) => strtolower($q['section_uuid']),
                    is_string($q['section'] ?? null) => ContentImporter::sectionUuid($slug, $q['section']),
                    default => null,
                };
                if ($uuid === null) {
                    $errors[] = "{$at}: section (a section key) or section_uuid is required: every question points at the theory that teaches it";

                    continue;
                }
                $refs[$at] = [$uuid, $lessonIds[$slug], $q['section'] ?? $q['section_uuid']];
            }
        }

        // One query for every reference: the section must exist and belong to the question's lesson.
        $found = LessonSection::query()->whereIn('uuid', array_column($refs, 0))->get(['id', 'uuid', 'lesson_id'])->keyBy('uuid');
        $sections = [];
        foreach ($refs as $at => [$uuid, $lessonId, $named]) {
            $section = $found[$uuid] ?? null;
            if (! $section || $section->lesson_id !== $lessonId) {
                $errors[] = "{$at}: section ".json_encode($named).' does not exist in this lesson';
            } else {
                $sections[$at] = $section->id;
            }
        }

        return [$errors, $sections];
    }
}

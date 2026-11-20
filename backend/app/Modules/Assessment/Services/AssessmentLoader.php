<?php

namespace App\Modules\Assessment\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestion;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads the assessment question bank from its content file (ARCHITECTURE D24).
 *
 * Same rules as the lesson importer: the whole file is validated before anything is written,
 * questions are matched by their stable key, and a question missing from the file is archived,
 * never deleted, because past answers refer to it. The order in the file is the order asked.
 */
final class AssessmentLoader
{
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    /**
     * @param  array{assessment: array<string, mixed>, questions: array<string, array<string, mixed>>}  $file
     * @return array{assessment: string, questions: int, archived: list<string>}
     *
     * @throws InvalidArgumentException listing every problem in the file
     */
    public function load(array $file): array
    {
        $domainIds = Domain::query()->pluck('id', 'slug');
        $errors = $this->validate($file, $domainIds->keys()->all());
        if ($errors) {
            throw new InvalidArgumentException("The assessment file is invalid:\n- ".implode("\n- ", $errors));
        }

        return DB::transaction(function () use ($file, $domainIds) {
            $meta = $file['assessment'];
            $assessment = Assessment::query()->updateOrCreate(['slug' => $meta['slug']], [
                'title' => $meta['title'],
                'description' => $meta['description'],
                'is_active' => true,
                'time_limit_minutes' => $meta['time_limit_minutes'] ?? null,
            ]);

            $position = 0;
            foreach ($file['questions'] as $key => $data) {
                $question = AssessmentQuestion::query()->updateOrCreate(
                    ['assessment_id' => $assessment->id, 'key' => $key],
                    [
                        'domain_id' => $domainIds[$data['domain']],
                        'difficulty' => $data['difficulty'],
                        'prompt' => $data['prompt'],
                        'code' => $data['code'] ?? null,
                        'explanation' => $data['explanation'],
                        'position' => ++$position,
                        'is_archived' => false,
                    ],
                );
                $this->syncOptions($question, $data['options'], $data['answer']);
            }

            $removed = $assessment->questions()->where('is_archived', false)->whereNotIn('key', array_keys($file['questions']));
            $archived = $removed->pluck('key')->all();
            $removed->update(['is_archived' => true]);

            // Only one assessment is offered at a time.
            Assessment::query()->whereKeyNot($assessment->id)->update(['is_active' => false]);

            return ['assessment' => $assessment->slug, 'questions' => $position, 'archived' => $archived];
        });
    }

    /**
     * Options are matched by position. Answers point at option rows, so the number of options of a
     * question that has been answered cannot change; a question that changes that much gets a new key.
     *
     * @param  list<string>  $labels
     */
    private function syncOptions(AssessmentQuestion $question, array $labels, int $answer): void
    {
        $existing = $question->options()->get()->keyBy('position');
        if ($existing->isNotEmpty() && $existing->count() !== count($labels)
            && AssessmentAnswer::query()->where('assessment_question_id', $question->id)->exists()) {
            throw new InvalidArgumentException("Question {$question->key} has been answered, so its number of options cannot change. Give the changed question a new key.");
        }

        foreach ($labels as $i => $label) {
            $question->options()->updateOrCreate(['position' => $i + 1], ['label' => $label, 'is_correct' => $i === $answer]);
        }
        $question->options()->where('position', '>', count($labels))->delete();
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  list<string>  $domains
     * @return list<string>
     */
    private function validate(array $file, array $domains): array
    {
        $errors = [];
        $meta = $file['assessment'] ?? null;
        foreach (['slug', 'title', 'description'] as $field) {
            if (! is_array($meta) || ! is_string($meta[$field] ?? null) || trim($meta[$field]) === '') {
                $errors[] = "assessment.{$field} is required";
            }
        }

        $questions = $file['questions'] ?? null;
        if (! is_array($questions) || $questions === []) {
            return [...$errors, 'questions must be a non-empty list keyed by question key'];
        }

        foreach ($questions as $key => $q) {
            $at = "question {$key}";
            if (! is_string($key) || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) || strlen($key) > 120) {
                $errors[] = "{$at}: the key must be lowercase words joined by hyphens (at most 120 characters)";
            }
            if (! in_array($q['domain'] ?? null, $domains, true)) {
                $errors[] = "{$at}: unknown domain ".json_encode($q['domain'] ?? null);
            }
            if (! in_array($q['difficulty'] ?? null, [1, 2, 3], true)) {
                $errors[] = "{$at}: difficulty must be 1, 2 or 3";
            }
            foreach (['prompt', 'explanation'] as $field) {
                if (! is_string($q[$field] ?? null) || trim($q[$field]) === '') {
                    $errors[] = "{$at}: {$field} is required";
                }
            }
            if (isset($q['code']) && (! is_string($q['code']) || trim($q['code']) === '')) {
                $errors[] = "{$at}: code must be a non-empty string when present";
            }

            $options = $q['options'] ?? null;
            if (! is_array($options) || ! array_is_list($options) || count($options) < self::MIN_OPTIONS || count($options) > self::MAX_OPTIONS) {
                $errors[] = "{$at}: options must be a list of ".self::MIN_OPTIONS.' to '.self::MAX_OPTIONS.' labels';

                continue;
            }
            foreach ($options as $i => $label) {
                if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 300) {
                    $errors[] = "{$at}: option {$i} must be text of 1 to 300 characters";
                }
            }
            if (count(array_unique($options)) !== count($options)) {
                $errors[] = "{$at}: options must be different from each other";
            }
            if (! is_int($q['answer'] ?? null) || $q['answer'] < 0 || $q['answer'] >= count($options)) {
                $errors[] = "{$at}: answer must be the index of one of its options";
            }
        }

        return $errors;
    }
}

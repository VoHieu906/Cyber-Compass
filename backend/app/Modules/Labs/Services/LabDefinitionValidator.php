<?php

namespace App\Modules\Labs\Services;

use App\Models\Domain;
use App\Models\Lesson;
use App\Modules\Labs\Engines\LabEngine;

/**
 * The one validator of a lab definition (D76): the admin editor, publishing, the content files,
 * `content:check-labs` and packages all call it. Metadata, then the engine's JSON Schema, then
 * the engine's reference checks, then the shape of the solutions, then (when asked) the replay of
 * every solution and decoy through the engine. Messages are keyed by path in the definition
 * (`scenario.checkpoints.0.path`) and name places, never the values an answer holds.
 */
final class LabDefinitionValidator
{
    public function __construct(
        private readonly SimulatedLabProvider $engines,
        private readonly LabScenarios $scenarios,
        private readonly LabSolutions $solutions,
    ) {}

    /**
     * @param  array<string, mixed>  $def  already normalized
     * @return array{errors: array<string, list<string>>, warnings: array<string, list<string>>}
     */
    public function check(array $def, bool $replay = false): array
    {
        $errors = [];
        $add = function (string $path, string $message) use (&$errors) {
            $errors[$path][] = $message;
        };

        foreach (array_diff(array_keys($def), LabDefinition::FIELDS) as $key) {
            $add((string) $key, 'Unknown field.');
        }
        $this->text($def, 'title', 200, $add);
        $this->text($def, 'summary', 500, $add);
        $this->text($def, 'briefing', 10000, $add);
        if (! is_string($def['domain'] ?? null) || ! Domain::query()->where('slug', $def['domain'])->exists()) {
            $add('domain', 'Choose one of the existing domains.');
        }
        if (($def['lesson'] ?? null) !== null && (! is_string($def['lesson']) || ! Lesson::query()->where('slug', $def['lesson'])->exists())) {
            $add('lesson', 'No lesson has this slug.');
        }
        if (! in_array($def['difficulty'] ?? null, [1, 2, 3], true)) {
            $add('difficulty', 'Difficulty is 1, 2 or 3.');
        }
        if (! is_int($def['estimated_minutes'] ?? null) || $def['estimated_minutes'] < 1 || $def['estimated_minutes'] > 600) {
            $add('estimated_minutes', 'Use a whole number of minutes from 1 to 600.');
        }

        $engine = $def['engine'] ?? null;
        $engineObject = is_string($engine) ? $this->engines->engineFor($engine) : null;
        if (! $engineObject) {
            $add('engine', 'Choose one of: '.implode(', ', $this->engines->engineKeys()).'.');
        } elseif (! is_array($def['scenario'] ?? null)) {
            $add('scenario', 'The scenario is an object.');
        } else {
            $this->scenario($engineObject, $def['scenario'], $add);
        }
        $this->solutionShape($def['solutions'] ?? null, $add);

        $warnings = [];
        if ($errors === [] && $replay) {
            $result = $this->solutions->replay(LabDefinition::lab($def));
            $errors = $result['errors'];
            $warnings = $result['warnings'];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** @param  array<string, mixed>  $def */
    private function text(array $def, string $field, int $max, callable $add): void
    {
        $value = $def[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
            $add($field, 'This field is required.');
        } elseif (mb_strlen($value) > $max) {
            $add($field, "Use at most {$max} characters.");
        }
    }

    /** @param  array<string, mixed>  $scenario */
    private function scenario(LabEngine $engine, array $scenario, callable $add): void
    {
        $schemaErrors = $this->scenarios->validate($engine->key(), $scenario);
        foreach ($schemaErrors as $path => $messages) {
            foreach ($messages as $message) {
                $add($path === '' ? 'scenario' : "scenario.{$path}", $message);
            }
        }
        if ($schemaErrors !== []) {
            return;
        }

        $seen = [];
        foreach ($scenario['checkpoints'] as $i => $checkpoint) {
            if (! in_array($checkpoint['kind'], ['answer', 'conclusion', ...$engine->checkpointKinds()], true)) {
                $add("scenario.checkpoints.{$i}.kind", 'This engine has no checkpoint of this kind.');
            }
            if (isset($seen[$checkpoint['id']])) {
                $add("scenario.checkpoints.{$i}.id", 'Another checkpoint has this id.');
            }
            $seen[$checkpoint['id']] = true;
            if ($checkpoint['kind'] === 'conclusion') {
                foreach ($this->conclusion($engine, $scenario, $checkpoint) as $field => $message) {
                    $add("scenario.checkpoints.{$i}.{$field}", $message);
                }
            }
        }
        foreach ($engine->validateScenario($scenario) as $path => $messages) {
            foreach ($messages as $message) {
                $add("scenario.{$path}", $message);
            }
        }
    }

    /**
     * The references of a `conclusion` checkpoint (D80): the answer is one of the options, and
     * what it cites is evidence this engine offers.
     *
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $checkpoint
     * @return array<string, string> message by field
     */
    private function conclusion(LabEngine $engine, array $scenario, array $checkpoint): array
    {
        $errors = [];
        $ids = array_column($checkpoint['options'], 'id');
        if (count($ids) !== count(array_unique($ids))) {
            $errors['options'] = 'Two options have the same id.';
        }
        if (! in_array($checkpoint['answer'], $ids, true)) {
            $errors['answer'] = 'The answer must be the id of one of the options.';
        }
        $citable = $engine->citableIds($scenario);
        if ($citable === []) {
            $errors['kind'] = 'This lab has nothing to cite, so it cannot have a conclusion.';
        } elseif (array_diff($checkpoint['evidence'], $citable) !== []) {
            $errors['evidence'] = 'Every item of evidence must be one this lab offers to cite.';
        }
        $min = $checkpoint['min_cited'] ?? 1;
        if ($min > count(array_unique($checkpoint['evidence']))) {
            $errors['min_cited'] = 'More than the evidence listed.';
        }
        if (isset($checkpoint['max_cited']) && $checkpoint['max_cited'] < $min) {
            $errors['max_cited'] = 'Less than the least a learner must cite.';
        }

        return $errors;
    }

    private function solutionShape(mixed $solutions, callable $add): void
    {
        if (! is_array($solutions) || array_diff(array_keys($solutions), ['solutions', 'decoys']) !== [] || ! is_array($solutions['solutions'] ?? null) || ! is_array($solutions['decoys'] ?? null)) {
            $add('solutions', 'Use an object with the lists "solutions" and "decoys".');

            return;
        }
        foreach (['solutions', 'decoys'] as $kind) {
            $list = $solutions[$kind];
            if (! array_is_list($list) || count($list) > LabSolutions::MAX_LISTED) {
                $add("solutions.{$kind}", 'Use a list of at most '.LabSolutions::MAX_LISTED.' entries.');

                continue;
            }
            foreach ($list as $i => $item) {
                $at = "solutions.{$kind}.{$i}";
                if (! is_array($item) || array_diff(array_keys($item), ['name', 'actions']) !== []) {
                    $add($at, 'Each entry has a "name" and a list of "actions".');
                } elseif (! is_string($item['name'] ?? null) || trim($item['name']) === '' || mb_strlen($item['name']) > 100) {
                    $add("{$at}.name", 'Give it a name of up to 100 characters.');
                } elseif (! is_array($item['actions'] ?? null) || ! array_is_list($item['actions']) || $item['actions'] === [] || count($item['actions']) > LabSolutions::MAX_ACTIONS) {
                    $add("{$at}.actions", 'Use a list of 1 to '.LabSolutions::MAX_ACTIONS.' actions.');
                } elseif (! collect($item['actions'])->every(fn ($a) => is_array($a))) {
                    $add("{$at}.actions", 'Each action is an object.');
                }
            }
        }
    }
}

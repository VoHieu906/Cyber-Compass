<?php

namespace App\Modules\Learning\Services;

use App\Enums\ExerciseQuestionType as Type;

/**
 * Everything type-specific about exercise questions, in one place (ARCHITECTURE D31):
 * checking a content-file entry, compiling it into what the learner sees (`payload`) and the
 * hidden key (`answer`), checking the shape of a learner's response, and grading it.
 *
 * File entry → payload / answer / response:
 *  - single_choice, scenario: options (2 to 6), answer index → {options[, scenario]} / {option} / {option: id}
 *  - multi_choice: options (3 to 6), answer indexes → {options} / {options} / {options: ids}
 *  - true_false: answer bool → {} / {value} / {value: bool}
 *  - fill_blank: text with one ___ per blank (1 to 4), answers: accepted texts per blank
 *    → {text, blanks} / {blanks: [[accepted]]} / {blanks: texts}
 *  - matching: pairs [left, right] (3 to 6) → {left, right (shuffled)} / {matches} / {matches: right id per left}
 *  - ordering: items in the right order (3 to 7) → {items (shuffled)} / {order} / {order: ids}
 *
 * Grading is all or nothing per question. Blanks ignore case and extra spaces.
 */
final class QuestionTypes
{
    public const BLANK = '___';

    public const MAX_TEXT = 300;

    public const MAX_BLANK_RESPONSE = 100;

    /**
     * Problems with one file entry, as messages; empty when it is valid.
     *
     * @param  array<string, mixed>  $q
     * @return list<string>
     */
    public function validate(array $q): array
    {
        $type = Type::tryFrom((string) ($q['type'] ?? ''));
        if (! $type) {
            return ['type must be one of: '.implode(', ', array_column(Type::cases(), 'value'))];
        }

        $errors = [];
        $texts = fn ($list, int $min, int $max, string $name) => is_array($list) && array_is_list($list) && count($list) >= $min && count($list) <= $max
            && collect($list)->every(fn ($t) => is_string($t) && trim($t) !== '' && mb_strlen($t) <= self::MAX_TEXT)
            ? (count(array_unique($list)) === count($list) ? null : "{$name} must be different from each other")
            : "{$name} must be a list of {$min} to {$max} texts of at most ".self::MAX_TEXT.' characters';

        switch ($type) {
            case Type::SingleChoice:
            case Type::Scenario:
                $errors[] = $texts($q['options'] ?? null, 2, 6, 'options');
                if (! is_int($q['answer'] ?? null) || $q['answer'] < 0 || $q['answer'] >= count((array) ($q['options'] ?? []))) {
                    $errors[] = 'answer must be the index of one of the options';
                }
                if ($type === Type::Scenario && (! is_string($q['scenario'] ?? null) || trim($q['scenario']) === '')) {
                    $errors[] = 'scenario is required';
                }
                break;
            case Type::MultiChoice:
                $errors[] = $texts($q['options'] ?? null, 3, 6, 'options');
                $answer = $q['answer'] ?? null;
                $count = count((array) ($q['options'] ?? []));
                if (! is_array($answer) || $answer === [] || count(array_unique($answer)) !== count($answer)
                    || ! collect($answer)->every(fn ($i) => is_int($i) && $i >= 0 && $i < $count)) {
                    $errors[] = 'answer must be a list of different option indexes';
                }
                break;
            case Type::TrueFalse:
                if (! is_bool($q['answer'] ?? null)) {
                    $errors[] = 'answer must be true or false';
                }
                break;
            case Type::FillBlank:
                $text = $q['text'] ?? null;
                $blanks = is_string($text) ? substr_count($text, self::BLANK) : 0;
                if ($blanks < 1 || $blanks > 4) {
                    $errors[] = 'text must contain 1 to 4 blanks written as '.self::BLANK;
                }
                $answers = $q['answers'] ?? null;
                if (! is_array($answers) || count($answers) !== $blanks
                    || ! collect($answers)->every(fn ($a) => is_array($a) && $a !== [] && collect($a)->every(fn ($t) => is_string($t) && trim($t) !== '' && mb_strlen($t) <= self::MAX_BLANK_RESPONSE))) {
                    $errors[] = 'answers must list the accepted texts for each blank';
                }
                break;
            case Type::Matching:
                $pairs = $q['pairs'] ?? null;
                if (! is_array($pairs) || ! array_is_list($pairs) || ! collect($pairs)->every(fn ($p) => is_array($p) && count($p) === 2)) {
                    $errors[] = 'pairs must be a list of [left, right]';
                    break;
                }
                $errors[] = $texts(array_column($pairs, 0), 3, 6, 'left items');
                $errors[] = $texts(array_column($pairs, 1), 3, 6, 'right items');
                break;
            case Type::Ordering:
                $errors[] = $texts($q['items'] ?? null, 3, 7, 'items');
                break;
        }

        return array_values(array_filter($errors));
    }

    /**
     * @param  array<string, mixed>  $q  a valid file entry
     * @return array{payload: array<string, mixed>, answer: array<string, mixed>}
     */
    public function compile(string $key, array $q): array
    {
        $labels = fn (array $list) => array_map(fn ($label, $id) => ['id' => $id, 'label' => $label], $list, array_keys($list));

        $compiled = match (Type::from($q['type'])) {
            Type::SingleChoice => ['payload' => ['options' => $labels($q['options'])], 'answer' => ['option' => $q['answer']]],
            Type::Scenario => ['payload' => ['scenario' => $q['scenario'], 'options' => $labels($q['options'])], 'answer' => ['option' => $q['answer']]],
            Type::MultiChoice => ['payload' => ['options' => $labels($q['options'])], 'answer' => ['options' => $this->sorted($q['answer'])]],
            Type::TrueFalse => ['payload' => [], 'answer' => ['value' => $q['answer']]],
            Type::FillBlank => [
                'payload' => ['text' => $q['text'], 'blanks' => count($q['answers'])],
                'answer' => ['blanks' => array_map(fn ($accepted) => array_values($accepted), $q['answers'])],
            ],
            Type::Matching => $this->compileMatching($key, $q['pairs']),
            Type::Ordering => $this->compileOrdering($key, $q['items']),
        };

        return $compiled;
    }

    /**
     * Why a response has the wrong shape for its question, or null when it is fine.
     * A null response means unanswered and is always allowed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function responseError(Type $type, array $payload, mixed $response): ?string
    {
        if ($response === null) {
            return null;
        }
        if (! is_array($response)) {
            return 'must be an object';
        }
        $ids = fn (string $list) => array_column($payload[$list] ?? [], 'id');

        return match ($type) {
            Type::SingleChoice, Type::Scenario => in_array($response['option'] ?? null, $ids('options'), true) ? null : 'option must be one of the question\'s options',
            Type::MultiChoice => is_array($response['options'] ?? null) && array_is_list($response['options'])
                && count(array_unique($response['options'])) === count($response['options'])
                && collect($response['options'])->every(fn ($id) => in_array($id, $ids('options'), true)) ? null : 'options must be different options of the question',
            Type::TrueFalse => is_bool($response['value'] ?? null) ? null : 'value must be true or false',
            Type::FillBlank => is_array($response['blanks'] ?? null) && array_is_list($response['blanks']) && count($response['blanks']) === ($payload['blanks'] ?? 0)
                && collect($response['blanks'])->every(fn ($t) => is_string($t) && mb_strlen($t) <= self::MAX_BLANK_RESPONSE) ? null : 'blanks must be one text of at most '.self::MAX_BLANK_RESPONSE.' characters per blank',
            Type::Matching => is_array($response['matches'] ?? null) && array_is_list($response['matches']) && count($response['matches']) === count($ids('left'))
                && collect($response['matches'])->every(fn ($id) => $id === null || in_array($id, $ids('right'), true)) ? null : 'matches must give one right item (or null) per left item',
            Type::Ordering => is_array($response['order'] ?? null) && array_is_list($response['order']) && $this->sorted($response['order']) === $this->sorted($ids('items'))
                ? null : 'order must list every item once',
        };
    }

    /**
     * @param  array<string, mixed>  $answer
     * @param  array<string, mixed>|null  $response  a response that passed responseError()
     */
    public function isCorrect(Type $type, array $answer, ?array $response): bool
    {
        if ($response === null) {
            return false;
        }

        return match ($type) {
            Type::SingleChoice, Type::Scenario => $response['option'] === $answer['option'],
            Type::MultiChoice => $this->sorted($response['options']) === $answer['options'],
            Type::TrueFalse => $response['value'] === $answer['value'],
            Type::FillBlank => collect($answer['blanks'])->every(
                fn (array $accepted, int $i) => in_array(self::normalize($response['blanks'][$i]), array_map(self::normalize(...), $accepted), true),
            ),
            Type::Matching => $response['matches'] === $answer['matches'],
            Type::Ordering => $response['order'] === $answer['order'],
        };
    }

    /**
     * A response that the key marks correct (the first accepted text for each blank). Used to seed
     * demonstration attempts and by tests, so neither restates the answer formats.
     *
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    public function correctResponse(Type $type, array $answer): array
    {
        return match ($type) {
            Type::FillBlank => ['blanks' => array_map(fn (array $accepted) => $accepted[0], $answer['blanks'])],
            default => $answer,
        };
    }

    /** Case and spacing do not matter in blanks. */
    public static function normalize(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    /** @param  list<array{0: string, 1: string}>  $pairs */
    private function compileMatching(string $key, array $pairs): array
    {
        // Right items are shown shuffled, with ids that are display positions, so an id says nothing about its pair.
        $order = $this->shuffle($key, count($pairs));
        $right = array_map(fn ($original, $position) => ['id' => $position, 'label' => $pairs[$original][1]], $order, array_keys($order));
        $positionOf = array_flip($order);

        return [
            'payload' => ['left' => array_map(fn ($p, $i) => ['id' => $i, 'label' => $p[0]], $pairs, array_keys($pairs)), 'right' => $right],
            'answer' => ['matches' => array_map(fn ($i) => $positionOf[$i], array_keys($pairs))],
        ];
    }

    /** @param  list<string>  $items  in the right order */
    private function compileOrdering(string $key, array $items): array
    {
        $order = $this->shuffle($key, count($items));
        $positionOf = array_flip($order);

        return [
            'payload' => ['items' => array_map(fn ($original, $position) => ['id' => $position, 'label' => $items[$original]], $order, array_keys($order))],
            'answer' => ['order' => array_map(fn ($i) => $positionOf[$i], array_keys($items))],
        ];
    }

    /**
     * A permutation of 0..n-1 that depends only on the question key (so re-seeding keeps it) and
     * is never the identity (so the shown order is never already the answer).
     *
     * @return list<int> $order[position] = original index
     */
    private function shuffle(string $key, int $n): array
    {
        $order = range(0, $n - 1);
        usort($order, fn ($a, $b) => crc32("{$key}:{$a}") <=> crc32("{$key}:{$b}") ?: $a <=> $b);
        if ($order === range(0, $n - 1)) {
            $order = [...array_slice($order, 1), $order[0]];
        }

        return $order;
    }

    /** @param  list<int>  $ids */
    private function sorted(array $ids): array
    {
        sort($ids);

        return array_values($ids);
    }
}

<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;

/**
 * A lab's definition as plain data (D74): everything an admin edits and a content file holds,
 * with the domain and lesson named by slug. Drafts, revisions, content files and packages all
 * carry this shape, so one validator checks it. Pure functions: nothing here reads the database
 * except fromLab().
 */
final class LabDefinition
{
    public const FIELDS = ['title', 'summary', 'briefing', 'domain', 'lesson', 'engine', 'difficulty', 'estimated_minutes', 'scenario', 'solutions'];

    /** @return array{solutions: list<mixed>, decoys: list<mixed>} */
    public static function noSolutions(): array
    {
        return ['solutions' => [], 'decoys' => []];
    }

    /** @return array<string, mixed> */
    public static function fromLab(Lab $lab): array
    {
        $lab->loadMissing(['domain:id,slug', 'lesson:id,slug']);

        return [
            'title' => $lab->title,
            'summary' => $lab->summary,
            'briefing' => $lab->briefing,
            'domain' => $lab->domain->slug,
            'lesson' => $lab->lesson?->slug,
            'engine' => $lab->engine,
            'difficulty' => $lab->difficulty,
            'estimated_minutes' => $lab->estimated_minutes,
            'scenario' => $lab->scenario,
            'solutions' => $lab->solutions ?? self::noSolutions(),
        ];
    }

    /**
     * The known fields only, strings trimmed, `provider` dropped when it is the default. Unknown
     * keys are kept (under their own name) so validation can name them instead of hiding them.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        unset($input['provider']);
        foreach (['title', 'summary', 'briefing'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        $input['lesson'] = $input['lesson'] ?? null;
        $input['solutions'] = $input['solutions'] ?? self::noSolutions();

        return $input;
    }

    /** An unsaved Lab that engines and the presenter can run on. @param  array<string, mixed>  $def */
    public static function lab(array $def, string $slug = 'preview'): Lab
    {
        return new Lab([
            'slug' => $slug,
            'title' => $def['title'],
            'summary' => $def['summary'],
            'briefing' => $def['briefing'],
            'engine' => $def['engine'],
            'provider' => 'simulated',
            'scenario' => $def['scenario'],
            'solutions' => $def['solutions'] ?? self::noSolutions(),
            'difficulty' => $def['difficulty'],
            'estimated_minutes' => $def['estimated_minutes'],
        ]);
    }

    /** Identifies the whole definition, for "did it change since the preview" checks. @param  array<string, mixed>  $def */
    public static function fingerprint(array $def): string
    {
        return hash('sha256', self::canonical($def));
    }

    /**
     * Changes when what an attempt's state was built from changes (D75): the engine or the
     * scenario, leaving out the words of a checkpoint (title, hint). Texts, difficulty and the
     * lesson link never restart anyone.
     *
     * @param  array<string, mixed>  $def
     */
    public static function breakingKey(array $def): string
    {
        $scenario = $def['scenario'];
        foreach ($scenario['checkpoints'] ?? [] as $i => $checkpoint) {
            unset($scenario['checkpoints'][$i]['title'], $scenario['checkpoints'][$i]['hint']);
        }

        return hash('sha256', self::canonical(['engine' => $def['engine'], 'scenario' => $scenario]));
    }

    private static function canonical(mixed $value): string
    {
        $sort = function (mixed $v) use (&$sort) {
            if (! is_array($v)) {
                return $v;
            }
            $v = array_map($sort, $v);
            if (! array_is_list($v)) {
                ksort($v);
            }

            return $v;
        };

        return (string) json_encode($sort($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}

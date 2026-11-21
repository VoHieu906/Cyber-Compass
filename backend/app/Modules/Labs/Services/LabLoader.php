<?php

namespace App\Modules\Labs\Services;

use App\Models\Domain;
use App\Models\Lab;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads labs from seeders/content/labs.php, with the same rules as the other loaders: the whole
 * file is validated first (each scenario by its provider and engine), labs upsert by slug, and
 * a lab that leaves the file is archived, never deleted, because attempts refer to it.
 */
final class LabLoader
{
    private const DEFAULTS = ['provider' => 'simulated'];

    public function __construct(private readonly LabProviders $providers) {}

    /**
     * @param  array<string, array<string, mixed>>  $labs  keyed by slug, in the order offered
     * @return array{labs: int, archived: list<string>}
     */
    public function load(array $labs): array
    {
        $domains = Domain::query()->pluck('id', 'slug');
        $lessons = Lesson::query()->whereIn('slug', array_filter(array_column($labs, 'lesson')))->pluck('id', 'slug');
        $errors = $this->validate($labs, $domains->all(), $lessons->all());
        if ($errors) {
            throw new InvalidArgumentException("The lab file is invalid:\n- ".implode("\n- ", $errors));
        }

        return DB::transaction(function () use ($labs, $domains, $lessons) {
            $position = 0;
            foreach ($labs as $slug => $lab) {
                Lab::query()->updateOrCreate(['slug' => $slug], [
                    'title' => $lab['title'],
                    'summary' => $lab['summary'],
                    'briefing' => $lab['briefing'],
                    'domain_id' => $domains[$lab['domain']],
                    'lesson_id' => isset($lab['lesson']) ? $lessons[$lab['lesson']] : null,
                    'engine' => $lab['engine'],
                    'provider' => $lab['provider'] ?? self::DEFAULTS['provider'],
                    'scenario' => $lab['scenario'],
                    'difficulty' => $lab['difficulty'],
                    'estimated_minutes' => $lab['estimated_minutes'],
                    'position' => ++$position,
                    'is_archived' => false,
                ]);
            }
            $removed = Lab::query()->where('is_archived', false)->whereNotIn('slug', array_keys($labs));
            $archived = $removed->pluck('slug')->all();
            $removed->update(['is_archived' => true]);

            return ['labs' => $position, 'archived' => $archived];
        });
    }

    /** @return list<string> */
    private function validate(array $labs, array $domains, array $lessons): array
    {
        $errors = [];
        foreach ($labs as $slug => $lab) {
            $at = "lab {$slug}";
            if (! is_string($slug) || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
                $errors[] = "{$at}: the slug must be lowercase words joined by hyphens";
            }
            foreach (['title', 'summary', 'briefing'] as $field) {
                if (! is_string($lab[$field] ?? null) || trim($lab[$field]) === '') {
                    $errors[] = "{$at}: {$field} is required";
                }
            }
            if (! isset($domains[$lab['domain'] ?? ''])) {
                $errors[] = "{$at}: unknown domain";
            }
            if (isset($lab['lesson']) && ! isset($lessons[$lab['lesson']])) {
                $errors[] = "{$at}: no lesson {$lab['lesson']}";
            }
            if (! in_array($lab['difficulty'] ?? null, [1, 2, 3], true)) {
                $errors[] = "{$at}: difficulty must be 1, 2 or 3";
            }
            if (! is_int($lab['estimated_minutes'] ?? null) || $lab['estimated_minutes'] < 1) {
                $errors[] = "{$at}: estimated_minutes is required";
            }
            $provider = $this->providers->byKey($lab['provider'] ?? self::DEFAULTS['provider']);
            if (! $provider) {
                $errors[] = "{$at}: unknown provider";

                continue;
            }
            foreach ($provider->validate((string) ($lab['engine'] ?? ''), is_array($lab['scenario'] ?? null) ? $lab['scenario'] : []) as $message) {
                $errors[] = "{$at}: {$message}";
            }
        }

        return $errors;
    }
}

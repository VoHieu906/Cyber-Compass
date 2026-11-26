<?php

namespace App\Modules\Labs\Services;

use App\Models\Domain;
use App\Models\Lab;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads labs from the content files (LabFiles), with the same rules as the other loaders: the
 * whole file is validated first (every definition by LabDefinitionValidator, with all solutions
 * replayed), labs upsert by slug, and a lab that leaves the files is archived, never deleted.
 *
 * Once an admin edits a lab (`updated_by` set, or a draft or a revision exists: a deleted admin account must not hand the lab back to the files) the database owns it and the
 * loader skips it unless forced (D73, like D19). Admin-created labs are never in the files, so
 * only file-owned labs are ever archived. The loader sets `position` only when it creates a lab:
 * the order is the admin's after that. A changed engine or scenario restarts learners' attempts
 * (D75), as a publish does.
 */
final class LabLoader
{
    public function __construct(private readonly LabDefinitionValidator $validator) {}

    /**
     * @param  array<string, array<string, mixed>>  $labs  keyed by slug, in the order offered
     * @return array{labs: int, archived: list<string>, skipped: list<string>}
     */
    public function load(array $labs, bool $force = false): array
    {
        $labs = array_map(fn ($lab) => LabDefinition::normalize($lab), $labs);
        $errors = $this->validate($labs);
        if ($errors) {
            throw new InvalidArgumentException("The lab file is invalid:\n- ".implode("\n- ", $errors));
        }

        return DB::transaction(function () use ($labs, $force) {
            $domains = Domain::query()->pluck('id', 'slug');
            $lessons = Lesson::query()->whereIn('slug', array_filter(array_column($labs, 'lesson')))->pluck('id', 'slug');
            $existing = Lab::query()->withExists(['draft', 'revisions'])->whereIn('slug', array_keys($labs))->get()->keyBy('slug');
            $skipped = [];
            $count = 0;
            foreach ($labs as $slug => $def) {
                $lab = $existing[$slug] ?? null;
                if ($lab && ! $force && ($lab->updated_by !== null || $lab->draft_exists || $lab->revisions_exists)) {
                    $skipped[] = $slug;

                    continue;
                }
                $count++;
                $values = [
                    'title' => $def['title'],
                    'summary' => $def['summary'],
                    'briefing' => $def['briefing'],
                    'domain_id' => $domains[$def['domain']],
                    'lesson_id' => $def['lesson'] ? $lessons[$def['lesson']] : null,
                    'engine' => $def['engine'],
                    'scenario' => $def['scenario'],
                    'solutions' => $def['solutions'],
                    'difficulty' => $def['difficulty'],
                    'estimated_minutes' => $def['estimated_minutes'],
                    'status' => 'published',
                    'is_archived' => false,
                    'updated_by' => null,
                ];
                if ($lab) {
                    $breaking = LabDefinition::breakingKey(LabDefinition::fromLab($lab)) !== LabDefinition::breakingKey($def);
                    $lab->update($values + ['scenario_version' => $lab->scenario_version + ($breaking ? 1 : 0)]);
                } else {
                    Lab::query()->create($values + ['slug' => $slug, 'position' => (int) Lab::query()->max('position') + 1]);
                }
            }
            $removed = Lab::query()->where('is_archived', false)->whereNull('updated_by')->whereDoesntHave('draft')->whereDoesntHave('revisions')->whereNotIn('slug', array_keys($labs));
            $archived = $removed->pluck('slug')->all();
            $removed->update(['is_archived' => true]);

            return ['labs' => $count, 'archived' => $archived, 'skipped' => $skipped];
        });
    }

    /** @return list<string> */
    private function validate(array $labs): array
    {
        $errors = [];
        foreach ($labs as $slug => $lab) {
            if (! is_string($slug) || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
                $errors[] = "lab {$slug}: the slug must be lowercase words joined by hyphens";
            }
            foreach ($this->validator->check($lab, replay: true)['errors'] as $path => $messages) {
                foreach ($messages as $message) {
                    $errors[] = "lab {$slug}: {$path}: {$message}";
                }
            }
        }

        return $errors;
    }
}

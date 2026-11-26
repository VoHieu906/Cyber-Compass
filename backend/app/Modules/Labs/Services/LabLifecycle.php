<?php

namespace App\Modules\Labs\Services;

use App\Models\Domain;
use App\Models\Lab;
use App\Models\LabDraft;
use App\Models\LabRevision;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Creating, saving drafts, publishing, restoring, archiving and ordering labs (D74). Every
 * change is audited by field name and version only: the scenario and the solutions hold answers
 * and never go into the audit log. An admin write makes the lab database-owned (D73).
 */
final class LabLifecycle
{
    public function __construct(
        private readonly LabWorkingCopy $copies,
        private readonly LabScenarios $scenarios,
        private readonly AuditLogger $audit,
    ) {}

    /** A new lab starts as an unpublished draft holding the engine's starter scenario and solutions. */
    public function create(User $actor, string $slug, string $engine, string $title, string $domain): Lab
    {
        $starter = $this->scenarios->starter($engine);
        $def = $this->copies->validate([
            'title' => $title,
            'summary' => 'Describe the lab in one or two sentences.',
            'briefing' => "Tell the learner the situation and what to find or fix.\n\nEverything here is simulated.",
            'domain' => $domain,
            'engine' => $engine,
            'difficulty' => 1,
            'estimated_minutes' => 15,
            'scenario' => $starter['scenario'],
            'solutions' => $starter['solutions'],
        ]);

        return DB::transaction(function () use ($actor, $slug, $def) {
            $lab = Lab::query()->create([
                'slug' => $slug,
                'title' => $def['title'],
                'summary' => $def['summary'],
                'briefing' => $def['briefing'],
                'domain_id' => Domain::query()->where('slug', $def['domain'])->value('id'),
                'engine' => $def['engine'],
                'scenario' => $def['scenario'],
                'solutions' => $def['solutions'],
                'difficulty' => $def['difficulty'],
                'estimated_minutes' => $def['estimated_minutes'],
                'position' => (int) Lab::query()->max('position') + 1,
                'status' => 'draft',
                'updated_by' => $actor->id,
            ]);
            LabDraft::query()->create(['lab_id' => $lab->id, 'snapshot' => $def, 'lock_version' => 1, 'updated_by' => $actor->id]);
            $this->audit->record($actor, 'lab.created', 'lab', $lab->id, ['slug' => $slug, 'engine' => $def['engine']]);

            return $lab;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the saved definition
     */
    public function saveDraft(User $actor, Lab $lab, int $lockVersion, array $input): array
    {
        $def = $this->copies->validate($input);

        return DB::transaction(function () use ($actor, $lab, $lockVersion, $def) {
            $draft = LabDraft::query()->where('lab_id', $lab->id)->lockForUpdate()->first();
            if (($draft?->lock_version ?? 0) !== $lockVersion) {
                throw new ConflictHttpException(__('This lab was changed by someone else. Reload to see their changes.'));
            }
            LabDraft::query()->updateOrCreate(['lab_id' => $lab->id], ['snapshot' => $def, 'lock_version' => $lockVersion + 1, 'updated_by' => $actor->id]);

            return $def;
        });
    }

    public function discardDraft(User $actor, Lab $lab): void
    {
        DB::transaction(function () use ($actor, $lab) {
            if ($lab->status === 'draft') {
                throw ValidationException::withMessages(['lab' => [__('A lab that was never published has nothing to go back to. Archive it instead.')]]);
            }
            LabDraft::query()->where('lab_id', $lab->id)->delete();
            $this->audit->record($actor, 'lab.draft_discarded', 'lab', $lab->id);
        });
    }

    public function archive(User $actor, Lab $lab, bool $archived): void
    {
        $lab->update(['is_archived' => $archived, 'updated_by' => $actor->id]);
        $this->audit->record($actor, $archived ? 'lab.archived' : 'lab.unarchived', 'lab', $lab->id);
    }

    /** @param  list<int>  $ids  every lab id, in the order wanted */
    public function reorder(User $actor, array $ids): void
    {
        DB::transaction(function () use ($actor, $ids) {
            $all = Lab::query()->lockForUpdate()->pluck('id')->all();
            if (count($ids) !== count($all) || array_diff($all, $ids) !== []) {
                throw ValidationException::withMessages(['ids' => [__('List every lab exactly once.')]]);
            }
            foreach ($ids as $i => $id) {
                Lab::query()->whereKey($id)->update(['position' => $i + 1]);
            }
            $this->audit->record($actor, 'lab.reordered', 'lab', null, ['count' => count($ids)]);
        });
    }

    /** Puts an earlier version into the draft; nothing goes live until it is published. */
    public function restore(User $actor, Lab $lab, LabRevision $revision): void
    {
        $snapshot = $this->copies->validate($revision->snapshot);
        DB::transaction(function () use ($actor, $lab, $revision, $snapshot) {
            $draft = LabDraft::query()->where('lab_id', $lab->id)->lockForUpdate()->first();
            LabDraft::query()->updateOrCreate(['lab_id' => $lab->id], ['snapshot' => $snapshot, 'lock_version' => ($draft?->lock_version ?? 0) + 1, 'updated_by' => $actor->id]);
            $this->audit->record($actor, 'lab.revision_restored', 'lab', $lab->id, ['version' => $revision->version]);
        });
    }
}

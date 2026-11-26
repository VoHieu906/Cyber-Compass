<?php

namespace App\Modules\Labs\Services;

use App\Models\Domain;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\LabDraft;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Makes a lab's draft live (D74, D75). The draft is validated again with every solution and
 * decoy replayed, so a lab that cannot be solved never reaches learners. When the publish changes
 * what an attempt's state was built from (the engine or the scenario, not a checkpoint's words),
 * `scenario_version` goes up and learners' attempts start over on their next visit; the admin must
 * confirm that after seeing how many attempts it touches.
 */
final class PublishLab
{
    public function __construct(
        private readonly LabWorkingCopy $copies,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * What publishing the working copy would do to learners.
     *
     * @return array{breaking: bool, in_progress: int, completed: int}
     */
    public function impact(Lab $lab): array
    {
        $breaking = $lab->status === 'published'
            && LabDefinition::breakingKey($this->copies->current($lab)) !== LabDefinition::breakingKey(LabDefinition::fromLab($lab));
        $attempts = LabAttempt::query()->where('lab_id', $lab->id)->where('action_count', '>', 0);

        return [
            'breaking' => $breaking,
            'in_progress' => (clone $attempts)->whereNull('completed_at')->count(),
            'completed' => (clone $attempts)->whereNotNull('completed_at')->count(),
        ];
    }

    public function publish(User $actor, Lab $lab, int $lockVersion, ?string $note, bool $confirmRestart): Lab
    {
        return DB::transaction(function () use ($actor, $lab, $lockVersion, $note, $confirmRestart) {
            $lab = Lab::query()->whereKey($lab->id)->lockForUpdate()->firstOrFail();
            $draft = LabDraft::query()->where('lab_id', $lab->id)->lockForUpdate()->first();
            if (! $draft) {
                throw ValidationException::withMessages(['lab' => [__('There is nothing to publish: this lab has no unpublished changes.')]]);
            }
            if ($draft->lock_version !== $lockVersion) {
                throw new ConflictHttpException(__('This lab was changed by someone else. Reload to see their changes.'));
            }
            $def = $this->copies->validate($draft->snapshot, replay: true);

            $impact = $this->impact($lab);
            if ($impact['breaking'] && ($impact['in_progress'] + $impact['completed']) > 0 && ! $confirmRestart) {
                throw ValidationException::withMessages(['confirm_restart' => [__('This change restarts the lab for :progress learners in progress (:completed finished it before, and keep that). Confirm to publish.', ['progress' => $impact['in_progress'], 'completed' => $impact['completed']])]]);
            }

            $lab->update([
                'title' => $def['title'],
                'summary' => $def['summary'],
                'briefing' => $def['briefing'],
                'domain_id' => Domain::query()->where('slug', $def['domain'])->value('id'),
                'lesson_id' => $def['lesson'] ? Lesson::query()->where('slug', $def['lesson'])->value('id') : null,
                'engine' => $def['engine'],
                'scenario' => $def['scenario'],
                'solutions' => $def['solutions'],
                'difficulty' => $def['difficulty'],
                'estimated_minutes' => $def['estimated_minutes'],
                'status' => 'published',
                'updated_by' => $actor->id,
                'scenario_version' => $lab->scenario_version + ($impact['breaking'] ? 1 : 0),
                'lock_version' => $lab->lock_version + 1,
            ]);
            $version = (int) $lab->revisions()->max('version') + 1;
            $lab->revisions()->create(['version' => $version, 'snapshot' => $def, 'note' => $note, 'created_by' => $actor->id]);
            $draft->delete();
            $this->audit->record($actor, 'lab.published', 'lab', $lab->id, ['version' => $version, 'restarts_attempts' => $impact['breaking'], 'attempts' => $impact['in_progress'] + $impact['completed']]);

            return $lab;
        });
    }
}

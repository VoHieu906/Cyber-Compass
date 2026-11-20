<?php

namespace App\Modules\Content\Actions;

use App\Models\Lesson;
use App\Models\LessonDraft;
use App\Models\User;
use App\Modules\Content\Services\LessonWorkingCopy;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Saves the whole working copy (metadata and ordered sections) into the lesson's draft.
 * Learners keep seeing the published lesson until it is published. Draft saves are not
 * audited one by one; publishing is (with the revision it writes).
 */
final class SaveDraft
{
    public function __construct(private readonly LessonWorkingCopy $copy) {}

    /** @param  array<string, mixed>  $input  request body without lock_version */
    public function handle(User $actor, Lesson $lesson, array $input, int $lockVersion): LessonDraft
    {
        return DB::transaction(function () use ($actor, $lesson, $input, $lockVersion) {
            $draft = self::lockedDraft($lesson);
            self::assertVersion($draft, $lockVersion);

            $snapshot = $this->copy->validate($lesson, $input);

            return self::store($lesson, $draft, $snapshot, $actor);
        });
    }

    /** The lesson's draft row, locked for this transaction (null when there is none). */
    public static function lockedDraft(Lesson $lesson): ?LessonDraft
    {
        $draft = LessonDraft::query()->where('lesson_id', $lesson->id)->lockForUpdate()->first();
        $lesson->setRelation('draft', $draft);

        return $draft;
    }

    /** @throws ConflictHttpException when someone saved since the client loaded the lesson */
    public static function assertVersion(?LessonDraft $draft, int $lockVersion): void
    {
        if (($draft?->lock_version ?? 0) !== $lockVersion) {
            throw new ConflictHttpException(__('Someone else changed this lesson since you opened it. Reload to see their changes, then make yours again.'));
        }
    }

    /** @param  array<string, mixed>  $snapshot */
    public static function store(Lesson $lesson, ?LessonDraft $draft, array $snapshot, User $actor): LessonDraft
    {
        $draft ??= new LessonDraft(['lesson_id' => $lesson->id, 'lock_version' => 0]);
        $draft->fill([
            'snapshot' => $snapshot,
            'lock_version' => $draft->lock_version + 1,
            'updated_by' => $actor->id,
        ])->save();
        $lesson->setRelation('draft', $draft);

        return $draft;
    }
}

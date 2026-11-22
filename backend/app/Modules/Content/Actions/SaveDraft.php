<?php

namespace App\Modules\Content\Actions;

use App\Models\Lesson;
use App\Models\LessonDraft;
use App\Models\User;
use App\Modules\Content\Documents\DocumentDrafts;
use App\Modules\Content\Services\LessonWorkingCopy;
use Illuminate\Support\Facades\DB;

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
            $draft = DocumentDrafts::locked($lesson);
            DocumentDrafts::assertVersion($lesson, $draft, $lockVersion);

            /** @var LessonDraft */
            return DocumentDrafts::store($lesson, $draft, $this->copy->validate($lesson, $input), $actor);
        });
    }
}

<?php

namespace App\Modules\Content\Events;

use App\Models\Lesson;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lesson went live for the first time (not a later edit). Dispatched after commit; Phase 6
 * recommends it to learners whose roadmap holds one of its topics (D34).
 */
final class LessonPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Lesson $lesson) {}
}

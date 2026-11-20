<?php

namespace App\Modules\Roadmap\Actions;

use App\Models\LearningPath;
use App\Models\User;
use App\Modules\Assessment\Services\AttemptResult;
use App\Modules\Roadmap\Services\RoadmapGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Builds a new roadmap version from the learner's career goal and latest submitted assessment.
 *
 * One transaction archives the active version and stores the new one, so a learner always has at
 * most one active path (also enforced by a unique index). Progress lives in lesson_progress and
 * is never copied, so it carries over to the new version.
 */
final class GenerateLearningPath
{
    public function __construct(
        private readonly RoadmapGenerator $generator,
        private readonly AttemptResult $results,
    ) {}

    public function handle(User $user): LearningPath
    {
        $career = $user->learningGoal()->with('career')->first()?->career;
        if (! $career || ! $career->is_published) {
            throw ValidationException::withMessages(['career' => __('Choose a career goal before building your roadmap.')]);
        }

        $attempt = $user->assessmentAttempts()->submitted()->latest('submitted_at')->latest('id')->first();
        $scores = $attempt
            ? array_map(fn (array $row) => $row['score'], $this->results->scores($attempt)['domains'])
            : null;
        $nodes = $this->generator->plan($career, $scores);

        return DB::transaction(function () use ($user, $career, $attempt, $nodes) {
            // Serialises concurrent requests for the same learner.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $user->learningPaths()->active()->update(['status' => 'archived']);
            $path = $user->learningPaths()->create([
                'career_id' => $career->id,
                'assessment_attempt_id' => $attempt?->id,
                'version' => (int) $user->learningPaths()->max('version') + 1,
                'status' => 'active',
                'generated_at' => now(),
            ]);
            $path->nodes()->createMany($nodes);

            return $path;
        });
    }
}

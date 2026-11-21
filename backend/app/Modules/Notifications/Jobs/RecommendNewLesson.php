<?php

namespace App\Modules\Notifications\Jobs;

use App\Enums\LessonProgressStatus;
use App\Enums\NotificationType;
use App\Models\Enrollment;
use App\Models\LearningPathNode;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Notifications\Notifications\LearningNotification;
use App\Modules\Notifications\Services\SendOnce;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Recommends a newly published lesson (D34) to learners whose active roadmap holds one of its
 * topics, and to learners enrolled in its course, unless they already completed it. Runs on the
 * queue in chunks, so publishing never waits on the fan-out. Each learner is told once per lesson.
 * The message names the roadmap topic when there is one, else the course.
 */
final class RecommendNewLesson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const CHUNK = 200;

    public function __construct(public readonly int $lessonId) {}

    public function handle(SendOnce $once): void
    {
        $lesson = Lesson::query()->visibleToLearners()->with(['topics:id,title', 'chapter.course:id,title'])->find($this->lessonId);
        if (! $lesson) {
            return;
        }
        $course = $lesson->chapter->course;

        // Learner id => the roadmap topic that makes the lesson relevant to them.
        $topicOf = LearningPathNode::query()
            ->whereIn('topic_id', $lesson->topics->pluck('id'))
            ->join('learning_paths', 'learning_paths.id', '=', 'learning_path_nodes.learning_path_id')
            ->where('learning_paths.status', 'active')
            ->pluck('learning_path_nodes.topic_id', 'learning_paths.user_id');
        $enrolled = Enrollment::query()->where('course_id', $course->id)->pluck('user_id');
        $userIds = $topicOf->keys()->merge($enrolled)->unique()->values();

        foreach ($userIds->chunk(self::CHUNK) as $ids) {
            $users = User::query()
                ->whereIn('id', $ids)
                ->whereNull('suspended_at')
                ->whereDoesntHave('lessonProgress', fn ($q) => $q->where('lesson_id', $lesson->id)->where('status', LessonProgressStatus::Completed))
                ->get();

            foreach ($users as $user) {
                if (! $once->claim($user, "recommendation:lesson:{$lesson->id}")) {
                    continue;
                }
                $topic = isset($topicOf[$user->id]) ? $lesson->topics->firstWhere('id', $topicOf[$user->id]) : null;
                $user->notify(new LearningNotification(
                    NotificationType::Recommendation,
                    ['lesson' => $lesson->title, 'slug' => $lesson->slug, 'context' => $topic->title ?? $course->title, 'via' => $topic ? 'roadmap' : 'course'],
                    "/learn/{$lesson->slug}",
                ));
            }
        }
    }
}

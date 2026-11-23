<?php

namespace App\Modules\Content\Actions;

use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Content\CourseMap\MapSchemas;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Saves a course map live (ARCHITECTURE D58): validated by MapSchemas, checked against the
 * lock_version the admin loaded (409 when someone saved since), audited with the whole map
 * before and after, and marked admin-owned so content imports leave it alone. Restoring writes
 * an earlier map from the audit log through the same save. Lesson references are not checked
 * here: a lesson that is gone later must never block saving (CourseMap::issues lists them).
 */
final class SaveCourseGraph
{
    public const AUDITED = ['course_graph.saved', 'course_graph.restored'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function save(User $actor, Course $course, mixed $input, int $lockVersion, string $action = 'course_graph.saved', array $context = []): CourseGraph
    {
        [$errors, $graph] = MapSchemas::courseGraph($input);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($actor, $course, $graph, $lockVersion, $action, $context) {
            $row = CourseGraph::query()->where('course_id', $course->id)->lockForUpdate()->first();
            if (($row->lock_version ?? 0) !== $lockVersion) {
                throw new ConflictHttpException(__('Someone else changed this course map since you opened it. Reload to see their changes, then make yours again.'));
            }
            $from = $row?->graph;
            $row ??= new CourseGraph(['course_id' => $course->id, 'lock_version' => 0]);
            $row->fill(['graph' => $graph, 'lock_version' => $row->lock_version + 1, 'updated_by' => $actor->id])->save();

            $this->audit->record($actor, $action, 'course', $course->id, [...$context, 'graph' => [$from, $graph]]);

            return $row;
        });
    }

    /** Puts back the map as it was before or after an audited change of this course's map. */
    public function restore(User $actor, Course $course, AdminAuditLog $entry, string $side, int $lockVersion): CourseGraph
    {
        $graph = in_array($entry->action, self::AUDITED, true) && $entry->subject_type === 'course' && $entry->subject_id === $course->id
            ? ($entry->changes['graph'][$side === 'before' ? 0 : 1] ?? null)
            : null;
        if ($graph === null) {
            throw ValidationException::withMessages(['entry' => __('This change has no course map to restore.')]);
        }

        return $this->save($actor, $course, $graph, $lockVersion, 'course_graph.restored', ['restored_from' => $entry->id, 'side' => $side]);
    }
}

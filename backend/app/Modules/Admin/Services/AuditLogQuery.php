<?php

namespace App\Modules\Admin\Services;

use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reads the audit log, newest first, and attaches a display label to each subject
 * (user name, lesson title) with one query per subject type, not one per row.
 */
final class AuditLogQuery
{
    public const DEFAULT_PER_PAGE = 25;

    /**
     * @param  array{filter?: array{actor?: ?int, subject_type?: ?string, subject_id?: ?int, action?: ?string}, per_page?: ?int}  $params
     * @return LengthAwarePaginator<int, AdminAuditLog>
     */
    public function search(array $params): LengthAwarePaginator
    {
        $filter = $params['filter'] ?? [];

        $page = AdminAuditLog::query()
            ->with('actor:id,name')
            ->when($filter['actor'] ?? null, fn (Builder $q, $id) => $q->where('actor_id', $id))
            ->when($filter['subject_type'] ?? null, fn (Builder $q, $type) => $q->where('subject_type', $type))
            ->when($filter['subject_id'] ?? null, fn (Builder $q, $id) => $q->where('subject_id', $id))
            ->when($filter['action'] ?? null, fn (Builder $q, $action) => $q->where('action', $action))
            ->orderByDesc('id')
            ->paginate($params['per_page'] ?? self::DEFAULT_PER_PAGE)
            ->withQueryString();

        $this->label($page->getCollection());

        return $page;
    }

    /** @return Collection<int, AdminAuditLog> */
    public function aboutUser(User $user, int $limit = 10): Collection
    {
        $logs = AdminAuditLog::query()->with('actor:id,name')
            ->where('subject_type', 'user')->where('subject_id', $user->id)
            ->orderByDesc('id')->limit($limit)->get();

        $this->label($logs);

        return $logs;
    }

    /** @param  Collection<int, AdminAuditLog>  $logs */
    public function label(Collection $logs): void
    {
        $sources = [
            'user' => fn (Collection $ids) => User::query()->whereIn('id', $ids)->pluck('name', 'id'),
            'lesson' => fn (Collection $ids) => Lesson::query()->whereIn('id', $ids)->pluck('title', 'id'),
            'chapter' => fn (Collection $ids) => CourseChapter::query()->whereIn('id', $ids)->pluck('title', 'id'),
            'course' => fn (Collection $ids) => Course::query()->whereIn('id', $ids)->pluck('title', 'id'),
            'media' => fn (Collection $ids) => LessonMedia::query()->whereIn('id', $ids)->pluck('alt', 'id'),
        ];

        foreach ($logs->groupBy('subject_type') as $type => $group) {
            $labels = isset($sources[$type]) ? $sources[$type]($group->pluck('subject_id')->filter()->unique()) : collect();
            foreach ($group as $log) {
                $log->setAttribute('subject_label', $labels[$log->subject_id] ?? null);
            }
        }
    }
}

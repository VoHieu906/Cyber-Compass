<?php

namespace App\Modules\Admin\Services;

use App\Enums\LessonProgressStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** The admin user list query and the data the user detail page needs. */
final class UserDirectory
{
    public const DEFAULT_PER_PAGE = 20;

    public function __construct(private readonly AuditLogQuery $audit) {}

    /**
     * @param  array{q?: ?string, filter?: array{role?: ?string, status?: ?string}, sort?: ?string, per_page?: ?int}  $params
     * @return LengthAwarePaginator<int, User>
     */
    public function search(array $params): LengthAwarePaginator
    {
        $query = User::query()->with('profile');
        $filter = $params['filter'] ?? [];

        if ($term = trim((string) ($params['q'] ?? ''))) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        $query->when($filter['role'] ?? null, fn (Builder $q, string $role) => $q->where('role', $role));

        match ($filter['status'] ?? null) {
            'active' => $query->whereNull('suspended_at'),
            'suspended' => $query->whereNotNull('suspended_at'),
            default => null,
        };

        $sort = $params['sort'] ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if ($column === 'last_login_at') {
            // Accounts that never signed in go last in both directions.
            $query->orderByRaw('last_login_at IS NULL');
        }
        $query->orderBy($column, $direction)->orderBy('id');

        return $query->paginate($params['per_page'] ?? self::DEFAULT_PER_PAGE)->withQueryString();
    }

    public function detail(User $user): User
    {
        $user->load('profile')->loadCount([
            'enrollments',
            'lessonProgress as completed_lessons_count' => fn (Builder $q) => $q->where('status', LessonProgressStatus::Completed),
        ]);

        $user->setAttribute('is_last_active_admin', $user->isAdmin() && ! $user->isSuspended()
            && User::query()->activeAdmins()->count() === 1);
        $user->setRelation('recent_audit', $this->audit->aboutUser($user));

        return $user;
    }
}

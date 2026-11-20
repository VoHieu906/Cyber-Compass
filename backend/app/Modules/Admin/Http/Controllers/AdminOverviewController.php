<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Admin\Http\Resources\AuditLogResource;
use App\Modules\Admin\Services\AuditLogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class AdminOverviewController extends Controller
{
    /**
     * Admin overview.
     *
     * Account and lesson counts, and the five most recent admin actions.
     */
    public function __invoke(AuditLogQuery $audit): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $week = now()->subDays(7);
        $lessons = Lesson::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json(['data' => [
            'users' => [
                'total' => User::query()->count(),
                'admins' => User::query()->where('role', UserRole::Admin)->count(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
                'new_last_7_days' => User::query()->where('created_at', '>=', $week)->count(),
                'active_last_7_days' => User::query()->where('last_login_at', '>=', $week)->count(),
            ],
            'lessons' => collect(LessonStatus::cases())
                ->mapWithKeys(fn (LessonStatus $s) => [$s->value => (int) ($lessons[$s->value] ?? 0)]),
            'recent_activity' => AuditLogResource::collection($audit->search(['per_page' => 5])->getCollection()),
        ]]);
    }
}

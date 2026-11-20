<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Http\Requests\AuditLogIndexRequest;
use App\Modules\Admin\Http\Resources\AuditLogResource;
use App\Modules\Admin\Services\AuditLogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class AuditLogController extends Controller
{
    /**
     * Admin audit log.
     *
     * Every mutating admin action, newest first. Filter by `filter[actor]` (user id),
     * `filter[subject_type]` (user, lesson, chapter), `filter[subject_id]` and `filter[action]`.
     */
    public function __invoke(AuditLogIndexRequest $request, AuditLogQuery $query): AnonymousResourceCollection
    {
        Gate::authorize('viewAuditLog', User::class);

        return AuditLogResource::collection($query->search($request->validated()));
    }
}

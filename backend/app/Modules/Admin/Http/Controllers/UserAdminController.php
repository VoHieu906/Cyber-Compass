<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Actions\ChangeUserRole;
use App\Modules\Admin\Actions\ReactivateUser;
use App\Modules\Admin\Actions\SendPasswordResetLink;
use App\Modules\Admin\Actions\SuspendUser;
use App\Modules\Admin\Http\Requests\ChangeRoleRequest;
use App\Modules\Admin\Http\Requests\SuspendUserRequest;
use App\Modules\Admin\Http\Requests\UserIndexRequest;
use App\Modules\Admin\Http\Resources\AdminUserDetailResource;
use App\Modules\Admin\Http\Resources\AdminUserResource;
use App\Modules\Admin\Services\UserDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class UserAdminController extends Controller
{
    public function __construct(private readonly UserDirectory $directory) {}

    /**
     * List users.
     *
     * Search name and email (`q`), filter by `filter[role]` and `filter[status]` (active, suspended),
     * sort with `sort` (name, created_at, last_login_at; prefix `-` for descending). Admins only.
     */
    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return AdminUserResource::collection($this->directory->search($request->validated()));
    }

    /**
     * User detail.
     *
     * Role, status, learning totals and the last 10 admin actions about this user. Admins only.
     */
    public function show(User $user): AdminUserDetailResource
    {
        Gate::authorize('view', $user);

        return new AdminUserDetailResource($this->directory->detail($user));
    }

    /**
     * Change a user's role.
     *
     * Ends the user's sessions. 422 when an admin demotes themselves or the last active admin.
     */
    public function updateRole(ChangeRoleRequest $request, User $user, ChangeUserRole $change): AdminUserDetailResource
    {
        Gate::authorize('changeRole', $user);

        $change->handle($request->user(), $user, $request->enum('role', UserRole::class));

        return new AdminUserDetailResource($this->directory->detail($user));
    }

    /**
     * Suspend a user.
     *
     * Blocks sign-in and ends every session. 422 for the admin's own account, the last
     * active admin, or an account that is already suspended.
     */
    public function suspend(SuspendUserRequest $request, User $user, SuspendUser $suspend): AdminUserDetailResource
    {
        Gate::authorize('suspend', $user);

        $suspend->handle($request->user(), $user, trim($request->string('reason')->toString()));

        return new AdminUserDetailResource($this->directory->detail($user));
    }

    /**
     * Reactivate a suspended user.
     */
    public function reactivate(Request $request, User $user, ReactivateUser $reactivate): AdminUserDetailResource
    {
        Gate::authorize('reactivate', $user);

        $reactivate->handle($request->user(), $user);

        return new AdminUserDetailResource($this->directory->detail($user));
    }

    /**
     * Send a password reset email.
     *
     * The same email the user could request themselves. Admins never see or set passwords.
     * 429 if a link was sent to this account in the last minute.
     */
    public function sendPasswordReset(Request $request, User $user, SendPasswordResetLink $send): JsonResponse
    {
        Gate::authorize('sendPasswordReset', $user);

        $send->handle($request->user(), $user);

        return response()->json(['data' => ['message' => __('A password reset link has been sent to this user.')]], 202);
    }
}

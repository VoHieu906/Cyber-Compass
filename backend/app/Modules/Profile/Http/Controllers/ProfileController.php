<?php

namespace App\Modules\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Profile\Actions\ChangePassword;
use App\Modules\Profile\Actions\StoreAvatar;
use App\Modules\Profile\Actions\UpdateProfile;
use App\Modules\Profile\Http\Requests\UpdatePasswordRequest;
use App\Modules\Profile\Http\Requests\UpdatePreferencesRequest;
use App\Modules\Profile\Http\Requests\UpdateProfileRequest;
use App\Modules\Profile\Http\Requests\UploadAvatarRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in user's own profile. Every route acts on `$request->user()`, so
 * there is no id in the URL and no way to address someone else's profile.
 */
final class ProfileController extends Controller
{
    /** Update personal information. */
    public function update(UpdateProfileRequest $request, UpdateProfile $action): UserResource
    {
        return new UserResource($action->handle($request->user(), $request->validated()));
    }

    /** Upload an avatar (JPEG, PNG or WebP, up to 2 MB). Stored as a 256 px square WebP. */
    public function storeAvatar(UploadAvatarRequest $request, StoreAvatar $action): UserResource
    {
        return new UserResource($action->handle($request->user(), $request->file('avatar')));
    }

    /** Remove the avatar. */
    public function destroyAvatar(Request $request, StoreAvatar $action): UserResource
    {
        return new UserResource($action->remove($request->user()));
    }

    /** Update language and theme. */
    public function updatePreferences(UpdatePreferencesRequest $request): UserResource
    {
        $user = $request->user();
        $user->profile()->updateOrCreate([], $request->validated());

        return new UserResource($user->load('profile'));
    }

    /** Change the password. Signs out all other sessions. */
    public function updatePassword(UpdatePasswordRequest $request, ChangePassword $action): Response
    {
        $action->handle($request, $request->user(), $request->string('password')->toString());

        return response()->noContent();
    }
}

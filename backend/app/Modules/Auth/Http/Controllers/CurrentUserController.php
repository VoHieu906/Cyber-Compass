<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    /**
     * The signed-in user.
     */
    public function __invoke(Request $request): UserResource
    {
        return new UserResource($request->user()->load('profile'));
    }
}

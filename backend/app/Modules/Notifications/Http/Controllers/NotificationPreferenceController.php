<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Services\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** In-app and email switches per notification type (D34). */
final class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferences $preferences) {}

    /** Every type with its channels; unchanged types show the defaults (in app on, email off). */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preferences->all($request->user())]);
    }

    /** Change one or more types. Types not sent keep their current setting. */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $this->preferences->update($request->user(), $request->validated('preferences'));

        return response()->json(['data' => $this->preferences->all($request->user())]);
    }
}

<?php

namespace App\Modules\Auth\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The signed-in user as the frontend sees it.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'profile' => [
                'avatar_url' => $profile?->avatar_path
                    ? Storage::disk(config('cybercompass.uploads_disk'))->url($profile->avatar_path)
                    : null,
                'phone' => $profile?->phone,
                'date_of_birth' => $profile?->date_of_birth?->format('Y-m-d'),
                'education_level' => $profile?->education_level?->value,
                'locale' => $profile?->locale?->value ?? 'en',
                'theme' => $profile?->theme?->value ?? 'system',
            ],
        ];
    }
}

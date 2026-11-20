<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A user as admins see them in lists. Contact details beyond the email (phone, date
 * of birth) are deliberately left out: admins don't need them to run the platform.
 *
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $avatar = $this->profile?->avatar_path;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'status' => $this->isSuspended() ? 'suspended' : 'active',
            'avatar_url' => $avatar ? Storage::disk(config('cybercompass.uploads_disk'))->url($avatar) : null,
            'email_verified' => $this->email_verified_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'is_self' => $request->user()?->is($this->resource) ?? false,
        ];
    }
}

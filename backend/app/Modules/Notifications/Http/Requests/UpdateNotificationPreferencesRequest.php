<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNotificationPreferencesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1', 'max:'.count(NotificationType::cases())],
            'preferences.*.type' => ['required', 'distinct', Rule::enum(NotificationType::class)],
            'preferences.*.in_app' => ['required', 'boolean'],
            'preferences.*.email' => ['required', 'boolean'],
        ];
    }
}

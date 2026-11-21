<?php

namespace App\Modules\Profile\Http\Requests;

use App\Enums\Locale;
use App\Enums\ThemePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'required', Rule::enum(Locale::class)],
            'theme' => ['sometimes', 'required', Rule::enum(ThemePreference::class)],
            // An IANA name such as Asia/Ho_Chi_Minh; decides "today" for streaks and reminders (D33).
            'timezone' => ['sometimes', 'required', 'string', 'max:64', 'timezone:all_with_bc'],
        ];
    }
}

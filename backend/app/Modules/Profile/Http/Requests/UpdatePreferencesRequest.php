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
        ];
    }
}

<?php

namespace App\Modules\Profile\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every field is optional, so "Set as my goal" can send the career alone; a field that is sent
 * replaces the stored value, and null clears it. Careers and domains are named by slug, like the
 * rest of the public content API.
 */
class UpdateLearningGoalRequest extends FormRequest
{
    public const MIN_WEEKLY_MINUTES = 30;

    public const MAX_WEEKLY_MINUTES = 2400;

    public function rules(): array
    {
        return [
            'career' => ['sometimes', 'nullable', 'string', Rule::exists('careers', 'slug')->where('is_published', true)],
            'self_level' => ['sometimes', 'nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'weekly_minutes' => ['sometimes', 'nullable', 'integer', 'min:'.self::MIN_WEEKLY_MINUTES, 'max:'.self::MAX_WEEKLY_MINUTES],
            'domains' => ['sometimes', 'array', 'max:8'],
            'domains.*' => ['string', 'distinct', Rule::exists('domains', 'slug')],
        ];
    }
}

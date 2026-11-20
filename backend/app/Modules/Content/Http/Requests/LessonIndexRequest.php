<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonIndexRequest extends FormRequest
{
    public const SORTS = ['-updated_at', 'title', 'course'];

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.course' => ['nullable', 'integer', 'min:1'],
            'filter.chapter' => ['nullable', 'integer', 'min:1'],
            'filter.status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'filter.has_draft' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

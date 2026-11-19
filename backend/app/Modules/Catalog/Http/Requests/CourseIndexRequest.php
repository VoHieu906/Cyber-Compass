<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseIndexRequest extends FormRequest
{
    public const SORTS = ['newest', 'title', '-title', 'difficulty', '-difficulty', 'duration', '-duration'];

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.domain' => ['nullable', 'string', Rule::exists('domains', 'slug')],
            'filter.difficulty' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'filter.favorited' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

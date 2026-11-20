<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLessonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'chapter_id' => ['required', 'integer', Rule::exists('course_chapters', 'id')],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('lessons', 'slug')],
        ];
    }

    public function messages(): array
    {
        return ['slug.regex' => __('Use lowercase letters, numbers and single hyphens.')];
    }
}

<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
{
    public const SUBJECT_TYPES = ['user', 'lesson', 'chapter', 'course', 'media'];

    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.actor' => ['nullable', 'integer', 'min:1'],
            'filter.subject_type' => ['nullable', Rule::in(self::SUBJECT_TYPES)],
            'filter.subject_id' => ['nullable', 'integer', 'min:1'],
            'filter.action' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

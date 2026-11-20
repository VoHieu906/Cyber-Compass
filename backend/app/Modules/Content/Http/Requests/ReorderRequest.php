<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Reorders chapters (`chapter_ids`) or lessons (`lesson_ids`): the full list in the new order. */
class ReorderRequest extends FormRequest
{
    public function rules(): array
    {
        $key = $this->has('chapter_ids') ? 'chapter_ids' : 'lesson_ids';

        return [
            $key => ['required', 'array', 'min:1', 'max:200'],
            "{$key}.*" => ['integer', 'min:1'],
        ];
    }
}

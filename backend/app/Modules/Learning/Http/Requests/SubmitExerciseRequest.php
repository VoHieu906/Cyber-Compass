<?php

namespace App\Modules\Learning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The envelope of an exercise attempt. Each response's shape is checked against its question type in `SubmitExercise`. */
class SubmitExerciseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array', 'max:50'],
            'answers.*' => ['array:question_id,response'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.response' => ['nullable'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0', 'max:86400'],
        ];
    }
}

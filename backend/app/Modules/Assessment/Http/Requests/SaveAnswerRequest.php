<?php

namespace App\Modules\Assessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveAnswerRequest extends FormRequest
{
    /** Ownership is checked in the controller through AssessmentAttemptPolicy (404 for others). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `present`, so the client must send the key; null clears the answer.
        return ['option_id' => ['present', 'nullable', 'integer']];
    }
}

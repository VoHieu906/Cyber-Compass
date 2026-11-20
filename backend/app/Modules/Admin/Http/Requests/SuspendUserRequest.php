<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuspendUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Shown to other admins in the audit log, never to the suspended user.
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ];
    }
}

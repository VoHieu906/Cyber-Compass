<?php

namespace App\Modules\Profile\Http\Requests;

use App\Enums\Locale;
use App\Enums\ThemePreference;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'required', Rule::enum(Locale::class)],
            'theme' => ['sometimes', 'required', Rule::enum(ThemePreference::class)],
            // An IANA name such as Asia/Ho_Chi_Minh; decides "today" for streaks and reminders (D33).
            'timezone' => ['sometimes', 'required', 'string', 'max:64', 'timezone:all_with_bc'],
            'compass_enabled' => ['sometimes', 'required', 'boolean'],
            // Shape only (D53): the action list belongs to the web app, so new actions need no backend release.
            'shortcuts' => ['sometimes', 'present', 'array', 'max:64', $this->shortcutMap(...)],
        ];
    }

    /** Each entry: an action id => null (disabled) or {code | key, mod?, ctrl?, alt?, shift?, meta?}. */
    private function shortcutMap(string $attribute, mixed $value, Closure $fail): void
    {
        if (array_is_list($value) && $value !== []) {
            $fail(__('validation.array', ['attribute' => $attribute]));

            return;
        }
        foreach ($value as $action => $binding) {
            if (! preg_match('/^[a-z][a-zA-Z0-9]*(\.[a-z][a-zA-Z0-9]*){0,3}$/', (string) $action) || strlen((string) $action) > 64) {
                $fail(__('validation.regex', ['attribute' => $attribute]));

                return;
            }
            if ($binding === null) {
                continue;
            }
            $valid = is_array($binding)
                && array_diff(array_keys($binding), ['code', 'key', 'mod', 'ctrl', 'alt', 'shift', 'meta']) === []
                && (isset($binding['code']) xor isset($binding['key']))
                && (! isset($binding['code']) || (is_string($binding['code']) && preg_match('/^[A-Za-z][A-Za-z0-9]{0,31}$/', $binding['code'])))
                && (! isset($binding['key']) || (is_string($binding['key']) && mb_strlen($binding['key']) === 1))
                && collect($binding)->except(['code', 'key'])->every(fn ($flag) => is_bool($flag));
            if (! $valid) {
                $fail(__('validation.array', ['attribute' => $attribute]));

                return;
            }
        }
    }
}

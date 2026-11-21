<?php

namespace App\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Caps a value's length in bytes rather than characters. Passwords use it because bcrypt reads
 * only the first 72 bytes: two longer passwords that share those bytes would both be accepted,
 * and 72 bytes can be as few as 18 characters in scripts that need 4 bytes per character.
 */
final class MaxBytes implements ValidationRule
{
    public function __construct(private readonly int $bytes) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->bytes) {
            $fail('validation.max_bytes')->translate(['max' => $this->bytes]);
        }
    }
}

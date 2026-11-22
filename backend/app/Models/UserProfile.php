<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\Locale;
use App\Enums\ThemePreference;
use Database\Factories\UserProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['avatar_path', 'phone', 'date_of_birth', 'education_level', 'locale', 'theme', 'timezone', 'compass_enabled', 'shortcuts'])]
class UserProfile extends Model
{
    /** @use HasFactory<UserProfileFactory> */
    use HasFactory;

    /** Mirrors the column default, so a profile made in memory reads the same as a saved one. */
    protected $attributes = ['compass_enabled' => true];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'education_level' => EducationLevel::class,
            'locale' => Locale::class,
            'theme' => ThemePreference::class,
            'compass_enabled' => 'boolean',
            'shortcuts' => 'array',
        ];
    }
}

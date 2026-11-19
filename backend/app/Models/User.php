<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// `role` is deliberately not fillable: it is never set from request input.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Mirrors the column default, so a freshly created model knows its role without a reload. */
    protected $attributes = [
        'role' => 'learner',
    ];

    /**
     * Emails and other notifications go out in the user's chosen language.
     */
    public function preferredLocale(): string
    {
        return $this->profile?->locale?->value ?? 'en';
    }

    /** @return HasOne<UserProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /** @return BelongsToMany<Course, $this> */
    public function favoriteCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_favorites')->withPivot('created_at');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}

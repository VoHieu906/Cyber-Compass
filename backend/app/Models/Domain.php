<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'description', 'position'])]
class Domain extends Model
{
    /** @return HasMany<Skill, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** @return HasMany<Topic, $this> */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }
}

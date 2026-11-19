<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'title', 'summary', 'description', 'responsibilities', 'is_published', 'position'])]
class Career extends Model
{
    /** @return BelongsToMany<Domain, $this> */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'career_domains')->withPivot('required_level');
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'career_skills');
    }

    /**
     * The roadmap template: topics grouped into stages.
     *
     * @return BelongsToMany<Topic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'career_topics')
            ->withPivot('stage', 'position')
            ->orderByPivot('stage')
            ->orderByPivot('position');
    }

    /** @param  Builder<Career>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'responsibilities' => 'array',
            'is_published' => 'boolean',
        ];
    }
}

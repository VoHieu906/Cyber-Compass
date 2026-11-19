<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['domain_id', 'slug', 'title', 'summary', 'level'])]
class Topic extends Model
{
    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'topic_lessons')
            ->withPivot('position', 'is_required')
            ->orderByPivot('position');
    }

    /** @return BelongsToMany<Topic, $this> */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'topic_prerequisites', 'topic_id', 'prerequisite_topic_id');
    }
}

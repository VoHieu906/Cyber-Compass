<?php

namespace App\Models;

use App\Enums\Difficulty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * What the learner is aiming for. `self_level` is the learner's own rating; it is shown beside
 * the measured result and never changes scoring. `weekly_minutes` gives the roadmap its estimate.
 */
#[Fillable(['user_id', 'career_id', 'self_level', 'weekly_minutes'])]
class LearningGoal extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Career, $this> */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /**
     * Areas of interest.
     *
     * @return BelongsToMany<Domain, $this>
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'learning_goal_domains');
    }

    /** The self-rating as a difficulty level (beginner, intermediate, advanced). */
    public function selfLevel(): ?Difficulty
    {
        return $this->self_level ? Difficulty::fromSlug($this->self_level) : null;
    }

    protected function casts(): array
    {
        return ['weekly_minutes' => 'integer'];
    }
}

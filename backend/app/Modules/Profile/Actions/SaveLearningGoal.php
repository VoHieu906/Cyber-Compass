<?php

namespace App\Modules\Profile\Actions;

use App\Models\Career;
use App\Models\Domain;
use App\Models\LearningGoal;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the learner's goal. Only the fields given change. Changing the career does
 * not rebuild the roadmap: the roadmap reports that it is out of date and the learner updates it
 * (ARCHITECTURE D26).
 */
final class SaveLearningGoal
{
    /** @param  array{career?: ?string, self_level?: ?string, weekly_minutes?: ?int, domains?: list<string>}  $data */
    public function handle(User $user, array $data): LearningGoal
    {
        return DB::transaction(function () use ($user, $data) {
            $attributes = Arr::only($data, ['self_level', 'weekly_minutes']);
            if (array_key_exists('career', $data)) {
                $attributes['career_id'] = $data['career'] === null ? null : Career::query()->where('slug', $data['career'])->value('id');
            }

            $goal = LearningGoal::query()->updateOrCreate(['user_id' => $user->id], $attributes);

            if (array_key_exists('domains', $data)) {
                $goal->domains()->sync(Domain::query()->whereIn('slug', $data['domains'])->pluck('id'));
            }

            return $goal;
        });
    }
}

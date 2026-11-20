<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Enums\ScoreBand;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Modules\Assessment\Actions\StartAttempt;
use App\Modules\Assessment\Http\Resources\AttemptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssessmentController extends Controller
{
    /**
     * The assessment on offer.
     *
     * Question counts per domain, my open attempt (to resume) and my latest result.
     */
    public function active(Request $request): JsonResponse
    {
        $assessment = Assessment::query()->active()->firstOrFail();
        $questions = $assessment->currentQuestions()->with('domain:id,slug,name,position')->get(['id', 'assessment_id', 'domain_id', 'position']);
        $attempts = fn () => $request->user()->assessmentAttempts()->where('assessment_id', $assessment->id);

        $open = $attempts()->whereNull('submitted_at')->withCount([
            'answers',
            'answers as answered_count' => fn ($q) => $q->whereNotNull('assessment_option_id'),
        ])->first();
        $latest = $attempts()->submitted()->latest('submitted_at')->latest('id')->first();

        $domains = $questions
            ->groupBy(fn (AssessmentQuestion $q) => $q->domain->slug)
            ->map(fn ($group) => $group->first()->domain->setAttribute('question_count', $group->count()))
            ->sortBy('position')
            ->map(fn ($domain) => ['slug' => $domain->slug, 'name' => $domain->name, 'question_count' => $domain->question_count])
            ->values();

        return response()->json(['data' => [
            'slug' => $assessment->slug,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'time_limit_minutes' => $assessment->time_limit_minutes,
            'question_count' => $questions->count(),
            'domains' => $domains,
            'open_attempt' => $open ? ['id' => $open->id, 'answered' => $open->answered_count, 'total' => $open->answers_count] : null,
            'latest_result' => $latest ? [
                'id' => $latest->id,
                'score' => $latest->score_percent,
                'band' => ScoreBand::forPercent($latest->score_percent)->value,
                'submitted_at' => $latest->submitted_at->toIso8601String(),
            ] : null,
        ]]);
    }

    /**
     * Start or resume an attempt.
     *
     * Returns my open attempt if there is one (200), otherwise a new one (201). Questions come
     * without the answer key.
     */
    public function start(Request $request, string $slug, StartAttempt $start): JsonResponse
    {
        $assessment = Assessment::query()->active()->where('slug', $slug)->firstOrFail();
        ['attempt' => $attempt, 'created' => $created] = $start($request->user(), $assessment);

        return (new AttemptResource(AttemptController::loadForTaking($attempt)))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }
}

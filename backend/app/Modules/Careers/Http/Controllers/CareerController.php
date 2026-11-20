<?php

namespace App\Modules\Careers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Course;
use App\Modules\Careers\Http\Resources\CareerDetailResource;
use App\Modules\Careers\Http\Resources\CareerSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CareerController extends Controller
{
    /**
     * List career paths.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return CareerSummaryResource::collection(
            Career::query()->published()->with('domains')->withCount('topics')->orderBy('position')->get(),
        );
    }

    /**
     * Career detail.
     *
     * Responsibilities, required domains and skills, suggested courses, and the roadmap template.
     *
     * @unauthenticated
     */
    public function show(string $slug): CareerDetailResource
    {
        $career = Career::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'domains',
                'skills',
                'topics' => fn ($q) => $q->with(['domain', 'prerequisites:id,slug'])
                    ->withCount(['lessons' => fn ($l) => $l->visibleToLearners()]),
            ])
            ->withCount('topics')
            ->firstOrFail();

        // Courses in the career's domains, the most important domains first.
        $levels = $career->domains->pluck('pivot.required_level', 'id');
        $career->setRelation('suggestedCourses', Course::query()
            ->published()
            ->whereIn('domain_id', $levels->keys())
            ->with('domain')
            ->withLessonStats()
            ->get()
            ->sortBy([
                fn (Course $a, Course $b) => $levels[$b->domain_id] <=> $levels[$a->domain_id],
                fn (Course $a, Course $b) => $a->difficulty->value <=> $b->difficulty->value,
            ])
            ->values());

        return new CareerDetailResource($career);
    }
}

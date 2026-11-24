<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\CourseGraph;
use App\Models\Lesson;
use App\Modules\Content\Actions\SaveCourseGraph;
use App\Modules\Content\CourseMap\CourseMap;
use App\Modules\Content\CourseMap\CourseMapSuggestions;
use App\Modules\Content\Http\Requests\LockVersionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The course map panel of the course structure screen (F3, ARCHITECTURE D57, D58). */
final class CourseGraphController extends Controller
{
    /**
     * Course map for editing.
     *
     * The stored map (null when none), `lock_version` to send back (0 when none), every lesson of
     * the course with its status and published architecture (problem, shape), the issues to look at
     * (unplaced, unconnected, dangling, link_outside, no_problem), suggestions that resolve them
     * (relation, placement, problem, shape, start_map; D63) and the latest audited changes.
     */
    public function show(Course $course, CourseMap $map): JsonResponse
    {
        Gate::authorize('manageStructure', Lesson::class);

        return self::panel($course, $map);
    }

    /**
     * Save the course map.
     *
     * Applies at once (no draft). Body: `graph` and `lock_version`. 409 when stale; 422 keyed
     * `graph.edges.2.type`. References to lessons that are not live are kept and listed as issues.
     */
    public function update(LockVersionRequest $request, Course $course, SaveCourseGraph $save, CourseMap $map): JsonResponse
    {
        Gate::authorize('manageStructure', Lesson::class);
        $save->save($request->user(), $course, $request->input('graph'), $request->integer('lock_version'));

        return self::panel($course, $map);
    }

    /**
     * Restore an earlier course map.
     *
     * `side`: the map as it was `after` (default) or `before` the audited change. Saved like any
     * edit, so it is audited and can itself be undone.
     */
    public function restore(Request $request, Course $course, AdminAuditLog $entry, SaveCourseGraph $save, CourseMap $map): JsonResponse
    {
        Gate::authorize('manageStructure', Lesson::class);
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'side' => ['nullable', 'in:before,after'],
        ]);
        $save->restore($request->user(), $course, $entry, $data['side'] ?? 'after', (int) $data['lock_version']);

        return self::panel($course, $map);
    }

    private static function panel(Course $course, CourseMap $map): JsonResponse
    {
        $row = CourseGraph::query()->where('course_id', $course->id)->first();
        $chapters = $course->chapters()->with(['lessons' => fn ($q) => $q->orderBy('position'), 'lessons.architecture'])->get();

        return response()->json(['data' => [
            'course' => ['id' => $course->id, 'slug' => $course->slug, 'title' => $course->title],
            'graph' => $row?->graph,
            'lock_version' => $row->lock_version ?? 0,
            'owned_by_admin' => $row?->updated_by !== null,
            'lessons' => $chapters->flatMap(fn (CourseChapter $c) => $c->lessons->map(fn (Lesson $l) => [
                'id' => $l->id,
                'slug' => $l->slug,
                'title' => $l->title,
                'status' => $l->status->value,
                'problem' => $l->architecture?->architecture['problem'] ?? null,
                'shape' => $l->architecture?->architecture['shape'] ?? null,
                // The published architecture, for the panel's live preview.
                'architecture' => $l->architecture?->architecture,
            ]))->values(),
            'issues' => $map->issues($course, $row?->graph),
            // Rule-based fixes for those issues (D63); nothing is applied until the admin saves.
            'suggestions' => app(CourseMapSuggestions::class)->forCourse($course, $row?->graph),
            'history' => AdminAuditLog::query()
                ->where('subject_type', 'course')->where('subject_id', $course->id)
                ->whereIn('action', SaveCourseGraph::AUDITED)
                ->with('actor:id,name')->orderByDesc('id')->limit(20)->get()
                ->map(fn (AdminAuditLog $e) => [
                    'id' => $e->id,
                    'action' => $e->action,
                    'at' => $e->created_at?->toIso8601String(),
                    'actor' => $e->actor?->name,
                    'has_before' => ($e->changes['graph'][0] ?? null) !== null,
                ]),
        ]]);
    }
}

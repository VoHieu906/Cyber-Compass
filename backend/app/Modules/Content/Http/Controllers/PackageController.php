<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Post;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Content\Packages\PackageExporter;
use App\Modules\Content\Packages\PackagePlanner;
use App\Modules\Content\Packages\PackageReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Content packages that need no stored file (F6): checking pasted JSON, and a lesson or post as a
 * .ccpkg.json download. Images stay media UUIDs here; the zip export carries the files.
 */
final class PackageController extends Controller
{
    /** Validate-only: errors, warnings and changes for pasted JSON. Nothing is stored. */
    public function check(Request $request, PackageReader $reader, PackagePlanner $planner): JsonResponse
    {
        Gate::authorize('create', Lesson::class);
        $request->validate(['json' => ['required', 'string']]);

        try {
            $package = $reader->fromString($request->string('json')->toString());
            $report = $planner->plan($package['envelope'], null)->toArray();
        } catch (ValidationException $e) {
            $report = ['kind' => null, 'generator' => null, 'valid' => false, 'errors' => $e->errors(), 'warnings' => [], 'documents' => []];
        }

        return response()->json(['data' => $report]);
    }

    /** The lesson's working copy as a package; `sections` (comma-separated keys) exports only those, as replace operations. */
    public function lesson(Request $request, Lesson $lesson, PackageExporter $exporter, AuditLogger $audit): Response
    {
        Gate::authorize('view', $lesson);

        return $this->download($request, $exporter->lesson($lesson, self::keys($request)), $lesson->slug, 'lesson', $lesson->id, $audit);
    }

    public function post(Request $request, Post $post, PackageExporter $exporter, AuditLogger $audit): Response
    {
        Gate::authorize('view', $post);

        return $this->download($request, $exporter->post($post, self::keys($request)), $post->slug, 'post', $post->id, $audit);
    }

    private function download(Request $request, array $envelope, string $slug, string $type, int $id, AuditLogger $audit): Response
    {
        $audit->record($request->user(), "{$type}.package_exported", $type, $id, ['format' => 'json', 'sections' => self::keys($request)]);
        $name = $slug.(self::keys($request) ? '.sections' : '').'.ccpkg.json';

        return response(PackageExporter::json($envelope), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /** @return ?list<string> */
    private static function keys(Request $request): ?array
    {
        $raw = trim((string) $request->query('sections', ''));

        return $raw === '' ? null : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}

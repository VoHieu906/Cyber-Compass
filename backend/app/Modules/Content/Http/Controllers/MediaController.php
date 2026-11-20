<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Modules\Content\Actions\StoreLessonMedia;
use App\Modules\Content\Http\Requests\UploadMediaRequest;
use App\Modules\Content\Http\Resources\MediaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class MediaController extends Controller
{
    /** Uploaded lesson images, newest first (for the image picker). */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lesson::class);

        return MediaResource::collection(LessonMedia::query()->latest('id')->paginate(24));
    }

    /**
     * Upload a lesson image.
     *
     * Multipart `image` (jpg, png or webp; 64 to 6000 px; up to 5 MB) and `alt` (required).
     * Re-encoded to WebP, at most 1600 px wide. SVG is rejected.
     */
    public function store(UploadMediaRequest $request, StoreLessonMedia $store): JsonResponse
    {
        Gate::authorize('uploadMedia', Lesson::class);

        $media = $store->handle($request->user(), $request->file('image'), trim($request->string('alt')->toString()));

        return (new MediaResource($media))->response()->setStatusCode(201);
    }
}

<?php

namespace App\Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Search\Http\Requests\SearchRequest;
use App\Modules\Search\Services\GlobalSearch;
use Illuminate\Http\JsonResponse;

final class SearchController extends Controller
{
    /**
     * Search courses, lessons and careers.
     *
     * `q` is 2 to 100 characters. Returns `{query, courses, lessons, careers}`, at most five of
     * each; open to guests.
     */
    public function __invoke(SearchRequest $request, GlobalSearch $search): JsonResponse
    {
        $term = trim((string) $request->validated('q'));

        return response()->json(['data' => ['query' => $term, ...$search->search($term)]]);
    }
}

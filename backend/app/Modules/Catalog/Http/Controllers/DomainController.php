<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Modules\Catalog\Http\Resources\DomainResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DomainController extends Controller
{
    /**
     * List knowledge domains.
     *
     * With the number of published courses in each.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return DomainResource::collection(
            Domain::query()
                ->withCount(['courses' => fn (Builder $q) => $q->where('is_published', true)])
                ->orderBy('position')
                ->get(),
        );
    }
}

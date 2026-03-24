<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContentItemResource;
use App\Http\Resources\ContentSetResource;
use App\Models\ContentSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ContentSetController extends Controller
{
    /**
     * List content sets visible to the current user (or guest).
     *
     * Passes a paginated collection of ContentSetResource to the
     * Inertia page ContentSets/Index.
     */
    public function index(): Response
    {
        $sets = ContentSet::visibleTo(Auth::user())
            ->with('contentType')
            ->paginate(20);

        return Inertia::render('ContentSets/Index', [
            'contentSets' => ContentSetResource::collection($sets),
        ]);
    }

    /**
     * Show a single content set and its paginated items.
     *
     * The set must be visible to the current user; 404 otherwise (enforced
     * by applying the same scope before the slug lookup).
     */
    public function show(Request $request, string $slug): Response
    {
        $set = ContentSet::visibleTo(Auth::user())
            ->with('contentType')
            ->where('slug', $slug)
            ->firstOrFail();

        $items = $set->contentItems()->paginate(20);

        return Inertia::render('ContentSets/Show', [
            'contentSet' => new ContentSetResource($set),
            'items'      => ContentItemResource::collection($items),
        ]);
    }
}

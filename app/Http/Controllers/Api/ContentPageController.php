<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\JsonResponse;

class ContentPageController extends Controller
{
    /** GET /api/pages — published pages for the footer. */
    public function index(): JsonResponse
    {
        $pages = ContentPage::published()
            ->where('show_in_footer', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['title', 'slug']);

        return response()->json(['data' => $pages]);
    }

    /** GET /api/pages/{slug} — one page, with shop details filled in. */
    public function show(string $slug): JsonResponse
    {
        $page = ContentPage::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => [
            'title' => $page->title,
            'slug' => $page->slug,
            'body' => $page->renderedBody(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]]);
    }
}

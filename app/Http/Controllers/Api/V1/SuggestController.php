<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Search\SearchSuggestions;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SearchSuggestionsResource;
use App\Http\Support\PublicFilters;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/{pais}/suggest?q=… Sin sesión y cacheable: la misma consulta da la misma
 * respuesta a todos, así que el CDN absorbe las pulsaciones repetidas.
 */
final class SuggestController extends Controller
{
    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 100;

    public function __invoke(Request $request, Country $pais, SearchSuggestions $suggestions): JsonResponse
    {
        $query = mb_substr(PublicFilters::param($request, 'q') ?? '', 0, self::MAX_LENGTH);

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return response()->json(['data' => null]);
        }

        return (new SearchSuggestionsResource($suggestions->for($pais, $query)))
            ->response()
            ->header('Cache-Control', 'public, max-age=60, s-maxage=600');
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Support\PublicSlugs;
use App\Domain\Search\DoctorSearchService;
use App\Domain\Search\QueryInterpreter;
use App\Domain\Search\SearchCriteria;
use App\Http\Controllers\Controller;
use App\Http\Support\PublicFilters;
use App\Http\Support\PublicUrl;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /{pais}/buscar: texto libre ("pediatra en Heredia que hable inglés") y filtros.
 *
 * QueryInterpreter traduce ?q= a criterios. Si el resultado tiene una página canónica
 * (especialidad + ciudad → listado; solo especialidad → su página), se redirige allí:
 * la búsqueda no compite en Google con los listados. Si no, se muestran los resultados
 * con lo que se entendió, como chips que se pueden quitar (enlaces: funcionan sin JS).
 *
 * Los parámetros explícitos (?especialidad=, ?ciudad=, ?idioma=, ?sub=) los generan
 * esos chips y mandan sobre lo interpretado.
 */
final class SearchController extends Controller
{
    private const MAX_QUERY_LENGTH = 200;

    public function __invoke(
        Request $request,
        Country $pais,
        QueryInterpreter $interpreter,
        DoctorSearchService $search,
    ): View|RedirectResponse {
        $criteria = $this->criteria($request, $pais, $interpreter);

        if ($criteria->isListing()) {
            return redirect(PublicUrl::forCriteria($criteria), 302);
        }

        if ($criteria->specialty !== null && $criteria->subspecialty === null && $criteria->city === null
            && $criteria->language === null && $criteria->text === null) {
            return redirect(PublicUrl::specialty($pais, $criteria->specialty), 302);
        }

        $doctors = $criteria->isEmpty() ? null : $search->search($criteria);

        if ($doctors !== null && $criteria->page > 1 && $criteria->page > $doctors->lastPage()) {
            abort(404);
        }

        $query = PublicFilters::param($request, 'q');

        return view('public.search', [
            'country' => $pais,
            'criteria' => $criteria,
            'query' => $query,
            'doctors' => $doctors,
            'chips' => $this->chips($criteria),
            'specialties' => $doctors === null || $doctors->total() === 0
                ? $search->specialtyFacets($pais)->take(12)
                : collect(),
            'breadcrumbs' => [
                ['name' => $pais->name, 'url' => PublicUrl::country($pais)],
                ['name' => 'Búsqueda', 'url' => PublicUrl::forCriteria($criteria)],
            ],
            'seo' => [
                'title' => $query !== null ? "Resultados para «{$query}»" : "Buscar médicos en {$pais->name}",
                'description' => "Busca médicos en {$pais->name} por especialidad, ciudad, idioma o nombre.",
                'canonical' => null,
                // Las búsquedas no se indexan: los listados son las páginas canónicas.
                'noindex' => true,
            ],
        ]);
    }

    private function criteria(Request $request, Country $country, QueryInterpreter $interpreter): SearchCriteria
    {
        $query = PublicFilters::param($request, 'q');
        $interpreted = $query !== null
            ? $interpreter->interpret($country, mb_substr($query, 0, self::MAX_QUERY_LENGTH))
            : new SearchCriteria($country);

        [$specialty, $subspecialty] = ($slug = PublicFilters::param($request, 'especialidad')) !== null
            ? SearchCriteria::specialtyPair(PublicSlugs::specialty($slug))
            : [$interpreted->specialty, $interpreted->subspecialty];

        $city = ($slug = PublicFilters::param($request, 'ciudad')) !== null
            ? PublicSlugs::city($country, $slug)
            : $interpreted->city;

        $language = ($code = PublicFilters::param($request, 'idioma')) !== null
            ? PublicFilters::language($code)
            : $interpreted->language;

        return new SearchCriteria(
            country: $country,
            specialty: $specialty,
            subspecialty: PublicFilters::subspecialty($specialty, PublicFilters::param($request, 'sub')) ?? $subspecialty,
            city: $city,
            language: $language,
            text: $interpreted->text,
            page: PublicFilters::page($request),
        );
    }

    /**
     * Lo que se entendió de la búsqueda, cada pieza con el enlace que la quita.
     *
     * @return array<int, array{kind: string, label: string, url: string}>
     */
    private function chips(SearchCriteria $criteria): array
    {
        $chips = [
            'specialty' => $criteria->specialty?->name,
            'subspecialty' => $criteria->subspecialty?->name,
            'city' => $criteria->city?->name,
            'language' => $criteria->language ? 'Habla '.mb_strtolower($criteria->language->name) : null,
            'text' => $criteria->text !== null ? "«{$criteria->text}»" : null,
        ];

        return collect($chips)
            ->filter()
            ->map(fn (string $label, string $kind) => [
                'kind' => $kind,
                'label' => $label,
                'url' => PublicUrl::forCriteria($criteria->without($kind)),
            ])
            ->values()
            ->all();
    }
}

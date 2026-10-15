<?php

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Support\PublicSlugs;
use App\Domain\Search\DoctorSearchService;
use App\Domain\Search\SearchCriteria;
use App\Http\Controllers\Controller;
use App\Http\Support\Breadcrumbs;
use App\Http\Support\PublicFilters;
use App\Http\Support\PublicUrl;
use App\Models\Country;
use App\Models\Doctor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listado /{pais}/{especialidad}/{ciudad}: la página SEO del directorio
 * ("cardiólogos en San José"). Los resultados van en el HTML inicial (ARQUITECTURA.md
 * §6.2); los filtros son GET y funcionan sin JavaScript.
 */
final class ListingController extends Controller
{
    public function __invoke(
        Request $request,
        Country $pais,
        string $especialidad,
        string $ciudad,
        DoctorSearchService $search,
    ): View|RedirectResponse {
        $specialty = PublicSlugs::specialty($especialidad) ?? abort(404);
        $city = PublicSlugs::city($pais, $ciudad) ?? abort(404);

        // Una subespecialidad no tiene listado propio: es un filtro del padre (§12 #2).
        if ($specialty->parent_id !== null) {
            return redirect(PublicUrl::listing($pais, $specialty->parent, $city, [
                'sub' => $specialty->slug,
                'idioma' => PublicFilters::param($request, 'idioma'),
            ]), 301);
        }

        if ($specialty->slug !== $especialidad || $city->slug !== $ciudad) {
            return redirect(PublicUrl::listing($pais, $specialty, $city, [
                'sub' => PublicFilters::param($request, 'sub'),
                'idioma' => PublicFilters::param($request, 'idioma'),
                'page' => PublicFilters::param($request, 'page'),
            ]), 301);
        }

        $criteria = new SearchCriteria(
            country: $pais,
            specialty: $specialty,
            subspecialty: PublicFilters::subspecialty($specialty, PublicFilters::param($request, 'sub')),
            city: $city,
            language: PublicFilters::language(PublicFilters::param($request, 'idioma')),
            page: PublicFilters::page($request),
        );

        $doctors = $search->search($criteria);

        if ($criteria->page > 1 && $criteria->page > $doctors->lastPage()) {
            abort(404);
        }

        $filtered = $criteria->subspecialty !== null || $criteria->language !== null;
        $total = $doctors->total();
        $baseUrl = PublicUrl::listing($pais, $specialty, $city);

        $breadcrumbs = [
            ['name' => $pais->name, 'url' => PublicUrl::country($pais)],
            ['name' => $specialty->name, 'url' => PublicUrl::specialty($pais, $specialty)],
            ['name' => $city->name, 'url' => $baseUrl],
        ];

        $title = "{$specialty->name} en {$city->name}";

        return view('public.listing', [
            'country' => $pais,
            'specialty' => $specialty,
            'city' => $city,
            'criteria' => $criteria,
            'doctors' => $doctors,
            'subspecialties' => PublicFilters::subspecialties($specialty),
            'languages' => PublicFilters::languages(),
            'filtered' => $filtered,
            'baseUrl' => $baseUrl,
            // Sin resultados: la misma especialidad en otras ciudades del país.
            'otherCities' => $total === 0 ? $search->cityFacets($criteria)->take(8) : collect(),
            'breadcrumbs' => $breadcrumbs,
            'seo' => [
                'title' => $title.', '.$pais->name.($criteria->page > 1 ? " · página {$criteria->page}" : ''),
                'description' => "Especialistas en {$specialty->name} en {$city->name}, {$pais->name}. "
                    .'Teléfono, WhatsApp y dirección de cada consultorio, sin intermediarios.',
                // Cada página de la paginación es canónica de sí misma; los filtros apuntan
                // a la URL base para no multiplicar variantes indexadas.
                'canonical' => url($filtered || $criteria->page === 1
                    ? $baseUrl
                    : PublicUrl::listing($pais, $specialty, $city, ['page' => $criteria->page])),
                'noindex' => $filtered || $total === 0,
            ],
            'jsonLd' => [
                Breadcrumbs::jsonLd($breadcrumbs),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'ItemList',
                    'name' => $title,
                    'numberOfItems' => $total,
                    'itemListElement' => collect($doctors->items())->values()->map(fn (Doctor $doctor, int $i) => [
                        '@type' => 'ListItem',
                        'position' => ($doctors->currentPage() - 1) * $doctors->perPage() + $i + 1,
                        'url' => url(PublicUrl::doctor($doctor)),
                        'name' => $doctor->displayName(),
                    ])->all(),
                ],
            ],
        ]);
    }
}

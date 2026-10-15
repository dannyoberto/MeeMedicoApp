<?php

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Support\PublicSlugs;
use App\Domain\Search\DoctorSearchService;
use App\Domain\Search\SearchCriteria;
use App\Http\Controllers\Controller;
use App\Http\Support\Breadcrumbs;
use App\Http\Support\PublicUrl;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * /{pais}/{especialidad}: en qué ciudades hay especialistas, y los primeros perfiles.
 * Es la página puente entre la home del país y los listados por ciudad.
 */
final class SpecialtyController extends Controller
{
    public function __invoke(Country $pais, string $especialidad, DoctorSearchService $search): View|RedirectResponse
    {
        $specialty = PublicSlugs::specialty($especialidad) ?? abort(404);

        if ($specialty->parent_id !== null) {
            return redirect(PublicUrl::specialty($pais, $specialty->parent), 301);
        }

        if ($specialty->slug !== $especialidad) {
            return redirect(PublicUrl::specialty($pais, $specialty), 301);
        }

        $criteria = new SearchCriteria($pais, $specialty);
        $doctors = $search->search($criteria, 6);

        $breadcrumbs = [
            ['name' => $pais->name, 'url' => PublicUrl::country($pais)],
            ['name' => $specialty->name, 'url' => PublicUrl::specialty($pais, $specialty)],
        ];

        return view('public.specialty', [
            'country' => $pais,
            'specialty' => $specialty,
            'cities' => $search->cityFacets($criteria),
            'doctors' => $doctors,
            'breadcrumbs' => $breadcrumbs,
            'seo' => [
                'title' => "{$specialty->name} en {$pais->name}: especialistas por ciudad",
                'description' => $specialty->description
                    ?: "Encuentra especialistas en {$specialty->name} en {$pais->name}. Elige tu ciudad y llama directo al consultorio.",
                'canonical' => url(PublicUrl::specialty($pais, $specialty)),
                'noindex' => $doctors->total() === 0,
            ],
            'jsonLd' => [Breadcrumbs::jsonLd($breadcrumbs)],
        ]);
    }
}

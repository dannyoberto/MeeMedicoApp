<?php

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\SpecialtyAliasKind;
use App\Domain\Search\DoctorSearchService;
use App\Domain\Search\SearchCriteria;
use App\Http\Controllers\Controller;
use App\Http\Support\PublicUrl;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * /{pais}: el buscador, las especialidades y las ciudades con médicos publicados.
 */
final class CountryHomeController extends Controller
{
    private const EXAMPLES = 3;

    private const FEATURED = 4;

    public function __invoke(Country $pais, DoctorSearchService $search): View
    {
        $specialties = $search->specialtyFacets($pais);
        $cities = $search->cityFacets(new SearchCriteria($pais));

        return view('public.country', [
            'country' => $pais,
            'specialties' => $specialties,
            'cities' => $cities->take(12),
            'total' => Doctor::where('country_id', $pais->id)->where('status', DoctorStatus::Active)->count(),
            'examples' => $this->examples($pais, $specialties, $search),
            // Los primeros del orden público: verificados y perfiles completos primero.
            // Son nombres en la home: DoctorPublicPaths purga /{pais} al cambiar una ficha.
            'featured' => collect($search->search(new SearchCriteria($pais), self::FEATURED)->items()),
            'seo' => [
                'title' => "Médicos en {$pais->name}: busca por especialidad y ciudad",
                'description' => "Directorio de médicos de {$pais->name}. Busca por especialidad, ciudad o nombre "
                    .'y contacta directo al consultorio por teléfono o WhatsApp.',
                'canonical' => url(PublicUrl::country($pais)),
                'noindex' => false,
            ],
        ]);
    }

    /**
     * Búsquedas de ejemplo escritas como habla la gente ("Pediatra en Heredia"), hechas
     * con datos reales del país: cada una lleva a un listado con resultados. Enseñan
     * que el buscador entiende frases, sin tutoriales.
     *
     * @param  Collection<int, array{specialty: Specialty, total: int}>  $specialties
     * @return array<int, array{label: string, url: string}>
     */
    private function examples(Country $country, Collection $specialties, DoctorSearchService $search): array
    {
        $top = $specialties->take(self::EXAMPLES)->pluck('specialty');

        $nouns = SpecialtyAlias::whereIn('specialty_id', $top->pluck('id'))
            ->where('kind', SpecialtyAliasKind::Seo)
            ->orderBy('created_at')
            ->get()
            ->groupBy('specialty_id')
            ->map(fn (Collection $aliases) => $aliases->first()->alias);

        return $top
            ->map(function ($specialty) use ($country, $search, $nouns) {
                $city = $search->cityFacets(new SearchCriteria($country, $specialty))->first()['city'] ?? null;

                if ($city === null) {
                    return null;
                }

                $label = Str::ucfirst($nouns[$specialty->id] ?? $specialty->name)." en {$city->name}";

                return ['label' => $label, 'url' => "/{$country->slug}/buscar?".http_build_query(['q' => $label])];
            })
            ->filter()
            ->values()
            ->all();
    }
}

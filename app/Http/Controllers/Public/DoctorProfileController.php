<?php

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Enums\FacilityStatus;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Support\PublicationRequirements;
use App\Domain\Directory\Support\PublicSlugs;
use App\Http\Controllers\Controller;
use App\Http\Support\Breadcrumbs;
use App\Http\Support\ContactLink;
use App\Http\Support\PublicUrl;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Ficha del médico: /{pais}/medicos/{slug}. La página más visitada y la más cacheable
 * del sitio (ARQUITECTURA.md §6.2): Blade puro, sin React.
 */
final class DoctorProfileController extends Controller
{
    public function __invoke(Country $pais, string $slug): View|RedirectResponse
    {
        $doctor = PublicSlugs::doctor($pais, $slug) ?? abort(404);

        if ($doctor->slug !== $slug) {
            return redirect(PublicUrl::doctor($doctor), 301);
        }

        $doctor->load([
            'country',
            'profile',
            'specialties' => fn ($q) => $q
                ->where('specialties.status', SpecialtyStatus::Active)
                ->orderByDesc('doctor_specialties.is_primary')
                ->orderBy('specialties.name'),
            'languages' => fn ($q) => $q->orderBy('languages.name'),
            'locations' => fn ($q) => $q
                ->where('locations.status', LocationStatus::Active)
                ->orderByDesc('doctor_locations.is_primary')
                ->with(['city.region', 'facility']),
            'contacts' => fn ($q) => $q->where('is_public', true)->orderByDesc('is_primary'),
        ]);

        $primarySpecialty = $doctor->specialties->first();
        $primaryLocation = $doctor->locations->first();
        $city = $primaryLocation?->city;
        $name = $doctor->displayName();

        // Las subespecialidades no tienen página: la miga apunta al listado del padre.
        $topSpecialty = $primarySpecialty?->parent_id ? $primarySpecialty->parent : $primarySpecialty;
        $listingUrl = $topSpecialty && $city ? PublicUrl::listing($pais, $topSpecialty, $city) : null;

        $breadcrumbs = [['name' => $pais->name, 'url' => PublicUrl::country($pais)]];
        if ($listingUrl) {
            $breadcrumbs[] = ['name' => $topSpecialty->name, 'url' => PublicUrl::specialty($pais, $topSpecialty)];
            $breadcrumbs[] = ['name' => $city->name, 'url' => $listingUrl];
        }
        $breadcrumbs[] = ['name' => $name, 'url' => PublicUrl::doctor($doctor)];

        $title = $primarySpecialty && $city
            ? "{$name}, {$primarySpecialty->name} en {$city->name}"
            : $name;

        $description = $doctor->profile?->headline
            ?: collect([
                $primarySpecialty?->name,
                $city ? "consulta en {$city->name}, {$pais->name}" : null,
                'Teléfono y dirección del consultorio',
            ])->filter()->implode(' · ').'.';

        return view('public.doctor', [
            'country' => $pais,
            'doctor' => $doctor,
            'name' => $name,
            'primarySpecialty' => $primarySpecialty,
            'primaryLocation' => $primaryLocation,
            'listingUrl' => $listingUrl,
            'breadcrumbs' => $breadcrumbs,
            'photoUrl' => PublicUrl::photo($doctor),
            'contacts' => ContactLink::forDoctor($doctor),
            'primaryContact' => ContactLink::primary($doctor),
            'secondaryContact' => ContactLink::secondary($doctor),
            'verified' => $doctor->verification_status === VerificationStatus::Verified,
            'operations' => self::operationsLinks($pais, $doctor, $name),
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => url(PublicUrl::doctor($doctor)),
                // La puerta de publicación impide fichas incompletas; esto es la red de
                // seguridad de AGENTS.md §3 si algo se colara.
                'noindex' => PublicationRequirements::missing($doctor) !== [],
            ],
            'jsonLd' => [self::physicianJsonLd($pais, $doctor, $name), Breadcrumbs::jsonLd($breadcrumbs)],
        ]);
    }

    /**
     * Correo y WhatsApp de operación del país, para que el médico corrija sus datos o
     * pida la baja (MODELO-DOMINIO.md §2.10). El mensaje lleva su nombre y su URL.
     *
     * @return array{email: string, whatsapp: string|null}
     */
    private static function operationsLinks(Country $country, Doctor $doctor, string $name): array
    {
        $message = "Hola, soy {$name}. Quiero corregir mis datos o solicitar la baja de mi perfil: ".url(PublicUrl::doctor($doctor));
        $whatsapp = config("meemedico.operations.whatsapp.{$country->code}");

        return [
            'email' => 'mailto:'.config('meemedico.operations.email')
                .'?subject='.rawurlencode("Mi perfil en MeeMedico: {$name}")
                .'&body='.rawurlencode($message),
            'whatsapp' => $whatsapp
                ? 'https://wa.me/'.preg_replace('/\D/', '', $whatsapp).'?text='.rawurlencode($message)
                : null,
        ];
    }

    /**
     * schema.org/Physician con sus consultorios. Solo datos visibles en la página.
     *
     * @return array<string, mixed>
     */
    private static function physicianJsonLd(Country $country, Doctor $doctor, string $name): array
    {
        $phone = $doctor->contacts->first(fn ($c) => in_array($c->type->value, ['phone', 'mobile', 'whatsapp'], true));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Physician',
            'name' => $name,
            'url' => url(PublicUrl::doctor($doctor)),
            'description' => $doctor->profile?->headline,
            'image' => PublicUrl::photo($doctor),
            'medicalSpecialty' => $doctor->specialties->pluck('name')->all() ?: null,
            'telephone' => $phone?->value_normalized ?: $phone?->value,
            'knowsLanguage' => $doctor->languages->pluck('code')->all() ?: null,
            'address' => $doctor->locations->map(fn (Location $location) => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $location->address,
                'addressLocality' => $location->city?->name,
                'addressRegion' => $location->city?->region?->name,
                'postalCode' => $location->postal_code,
                'addressCountry' => $country->code,
            ]))->all() ?: null,
            'hospitalAffiliation' => $doctor->locations
                ->pluck('facility')
                ->filter(fn ($facility) => $facility?->status === FacilityStatus::Active)
                ->unique('id')
                ->map(fn ($facility) => ['@type' => 'MedicalOrganization', 'name' => $facility->name])
                ->values()->all() ?: null,
        ]);
    }
}

<?php

namespace App\Http\Support;

use App\Domain\Search\SearchCriteria;
use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Support\Facades\Storage;

/**
 * URLs del sitio público, relativas a la raíz (ARQUITECTURA.md §10). Un solo sitio que
 * las construye: si cambian, cambian aquí y en DoctorPublicPaths (la purga del CDN).
 *
 * El país va por su slug ("costa-rica"), nunca por el código ISO.
 */
final class PublicUrl
{
    public static function country(Country $country): string
    {
        return "/{$country->slug}";
    }

    public static function doctor(Doctor $doctor): string
    {
        $doctor->loadMissing('country:id,slug');

        return "/{$doctor->country->slug}/medicos/{$doctor->slug}";
    }

    /**
     * Foto de perfil en el disco de medios (meemedico.media_disk), o null.
     */
    public static function photo(Doctor $doctor): ?string
    {
        $path = $doctor->profile?->profile_photo_path;

        return $path ? Storage::disk(config('meemedico.media_disk'))->url($path) : null;
    }

    public static function specialty(Country $country, Specialty $specialty): string
    {
        return "/{$country->slug}/{$specialty->slug}";
    }

    /**
     * @param  array<string, string|int|null>  $query  sub, idioma, page
     */
    public static function listing(Country $country, Specialty $specialty, City $city, array $query = []): string
    {
        return "/{$country->slug}/{$specialty->slug}/{$city->slug}".self::query($query);
    }

    /**
     * La URL que muestra un criterio: el listado canónico si es especialidad + ciudad,
     * la página de búsqueda en cualquier otro caso.
     */
    public static function forCriteria(SearchCriteria $criteria): string
    {
        $page = $criteria->page > 1 ? $criteria->page : null;

        if ($criteria->isListing()) {
            return self::listing($criteria->country, $criteria->specialty, $criteria->city, [
                'sub' => $criteria->subspecialty?->slug,
                'idioma' => $criteria->language?->code,
                'page' => $page,
            ]);
        }

        return "/{$criteria->country->slug}/buscar".self::query([
            'q' => $criteria->text,
            'especialidad' => $criteria->specialty?->slug,
            'sub' => $criteria->subspecialty?->slug,
            'ciudad' => $criteria->city?->slug,
            'idioma' => $criteria->language?->code,
            'page' => $page,
        ]);
    }

    /**
     * @param  array<string, string|int|null>  $query
     */
    private static function query(array $query): string
    {
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

        return $query === [] ? '' : '?'.http_build_query($query);
    }
}

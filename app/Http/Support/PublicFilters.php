<?php

namespace App\Http\Support;

use App\Domain\Directory\Enums\LanguageStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Models\Language;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filtros GET de listado y búsqueda (?sub=, ?idioma=, ?page=). Son parte de la URL que
 * cachea el CDN: un valor que no existe se ignora en lugar de dar error, y nunca se
 * crea una variante de página por un parámetro basura.
 */
final class PublicFilters
{
    /**
     * Página máxima aceptada: más allá no hay listados reales y solo sirve a un rastreador.
     */
    private const MAX_PAGE = 500;

    public static function param(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public static function page(Request $request): int
    {
        $page = filter_var(self::param($request, 'page'), FILTER_VALIDATE_INT);

        return $page === false ? 1 : max(1, min($page, self::MAX_PAGE));
    }

    /**
     * Subespecialidad: solo si es hija activa de la especialidad del listado.
     */
    public static function subspecialty(?Specialty $parent, ?string $slug): ?Specialty
    {
        if ($parent === null || $slug === null) {
            return null;
        }

        return Specialty::where('parent_id', $parent->id)
            ->where('slug', $slug)
            ->where('status', SpecialtyStatus::Active)
            ->first();
    }

    public static function language(?string $code): ?Language
    {
        return $code === null ? null : self::languages()->firstWhere('code', $code);
    }

    /**
     * Idiomas que se ofrecen como filtro. Sin español: lo hablan todos y filtrar por él
     * dejaría fuera a quien no lo tiene registrado.
     *
     * @return Collection<int, Language>
     */
    public static function languages(): Collection
    {
        return Language::where('status', LanguageStatus::Active)
            ->where('code', '!=', 'es')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Specialty>
     */
    public static function subspecialties(Specialty $parent): Collection
    {
        return $parent->children()
            ->where('status', SpecialtyStatus::Active)
            ->orderBy('name')
            ->get();
    }
}

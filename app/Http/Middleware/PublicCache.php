<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de caché de las páginas públicas (ARQUITECTURA.md §3 y §8.1).
 *
 * - URL sin query string (ficha, listado, especialidad): el CDN la guarda un día. La
 *   frescura no depende de ese plazo, sino de la purga por URL que despachan las
 *   Actions (DoctorCachePurge / DoctorPublicPaths).
 * - URL con query string (?page=, filtros, /buscar?q=): no se pueden purgar una a una
 *   porque no se pueden enumerar, así que viven diez minutos. Es el retraso máximo con
 *   el que un médico suspendido deja de aparecer en ellas.
 * - Errores: cinco minutos. Un 404 por un médico que se publica mañana no puede
 *   quedarse un día en el borde.
 *
 * El navegador guarda como mucho cinco minutos: a él no le llega la purga.
 */
final class PublicCache
{
    private const PURGEABLE = 'public, max-age=300, s-maxage=86400';

    private const VARIANT = 'public, max-age=300, s-maxage=600';

    private const ERROR = 'public, max-age=60, s-maxage=300';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable()) {
            return $response;
        }

        $cacheControl = match (true) {
            ! $response->isSuccessful() && ! $response->isRedirection() => self::ERROR,
            $request->getQueryString() !== null => self::VARIANT,
            default => self::PURGEABLE,
        };

        $response->headers->set('Cache-Control', $cacheControl);

        return $response;
    }
}

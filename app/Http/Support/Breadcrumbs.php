<?php

namespace App\Http\Support;

/**
 * Migas de pan de una página pública: las mismas en el HTML y en el JSON-LD
 * (schema.org/BreadcrumbList), para que Google muestre la ruta en el resultado.
 *
 * Cada miga: ['name' => …, 'url' => ruta relativa]. La última es la página actual.
 */
final class Breadcrumbs
{
    /**
     * @param  array<int, array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function jsonLd(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => url($crumb['url']),
            ], array_values($crumbs), array_keys(array_values($crumbs))),
        ];
    }
}

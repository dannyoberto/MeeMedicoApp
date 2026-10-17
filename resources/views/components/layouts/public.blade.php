{{--
    Layout del sitio público (ARQUITECTURA.md §6.2). Sin sesión, sin CSRF, sin nada que
    dependa de quién visita: la página entera se cachea en el CDN.

    $seo: title, description, canonical (URL absoluta o null), noindex.
    $jsonLd: bloques schema.org, ya como arrays.
    $contactBar: la página lleva la barra fija de contacto móvil; se reserva su alto.
--}}
@props([
    'country' => null,
    'seo' => [],
    'jsonLd' => [],
    'headerSearch' => true,
    'contactBar' => false,
])
@php
    $title = $seo['title'] ?? 'MeeMedico';
    $title = str_contains($title, 'MeeMedico') ? $title : "{$title} | MeeMedico";
    $homeUrl = $country ? App\Http\Support\PublicUrl::country($country) : '/';
@endphp
<!DOCTYPE html>
<html lang="{{ $country ? 'es-'.$country->code : 'es' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    @if (! empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    <meta name="robots" content="{{ ($seo['noindex'] ?? false) ? 'noindex, follow' : 'index, follow' }}">
    @if (! empty($seo['canonical']))
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="MeeMedico">
    <meta property="og:title" content="{{ $title }}">
    @if (! empty($seo['description']))
        <meta property="og:description" content="{{ $seo['description'] }}">
    @endif
    <meta property="og:locale" content="{{ $country ? 'es_'.$country->code : 'es_LA' }}">

    <link rel="icon" href="/images/brand/isotipo-64.png" type="image/png" sizes="64x64">
    <link rel="apple-touch-icon" href="/images/brand/isotipo-180.png">
    <link rel="preload" href="/fonts/InterVariable-latin.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    {{-- Precarga la ficha o el listado al pasar el cursor o el dedo (Speculation Rules):
         navegación casi instantánea sin JavaScript. Son páginas sin sesión y cacheadas
         en el CDN, así que prerenderizarlas es seguro. --}}
    <script type="speculationrules">
        {"prerender": [{"where": {"and": [
            {"href_matches": "/*"},
            {"not": {"href_matches": "/*/buscar*"}},
            {"not": {"selector_matches": "[data-contact], [target=_blank]"}}
        ]}, "eagerness": "moderate"}]}
    </script>

    @foreach ($jsonLd as $block)
        <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
</head>
<body @class(['flex min-h-dvh flex-col', 'has-contact-bar' => $contactBar])>
    <a href="#contenido" class="skip-link">Saltar al contenido</a>

    <header class="border-b border-border bg-surface">
        <div class="mx-auto flex h-16 max-w-content items-center gap-4 px-4">
            <a href="{{ $homeUrl }}" class="shrink-0" aria-label="MeeMedico, inicio">
                <img src="/images/brand/logo-horizontal-ink@2x.png" alt="MeeMedico" width="91" height="32" class="h-8 w-auto">
            </a>

            @if ($country && $headerSearch)
                <div class="ml-auto hidden w-full max-w-md md:block">
                    <x-public.search-form :country="$country" id="q-header" size="sm" :enhance="false" />
                </div>
                <a href="/{{ $country->slug }}/buscar" class="btn btn-ghost ml-auto md:hidden" aria-label="Buscar médicos">
                    <x-heroicon-o-magnifying-glass class="size-6" aria-hidden="true" />
                </a>
            @endif
        </div>
    </header>

    <main id="contenido" {{ $attributes->class('flex-1') }}>
        {{ $slot }}
    </main>

    <footer class="mt-16 bg-surface-inverse text-fg-inverse">
        <div class="mx-auto grid max-w-content gap-8 px-4 py-10 md:grid-cols-[minmax(0,1fr)_auto]">
            <div class="max-w-prose">
                {{-- El logo en oro solo va sobre petróleo oscuro (design-system §13). --}}
                <img src="/images/brand/logo-horizontal-gold@2x.png" alt="MeeMedico" width="91" height="32" class="h-8 w-auto" loading="lazy">
                <p class="mt-4 text-sm text-fg-inverse/80">
                    Directorio de médicos para encontrar al especialista que necesitas y contactarlo directo,
                    sin intermediarios. MeeMedico no presta servicios médicos ni gestiona citas.
                </p>
            </div>
            <nav aria-label="Países" class="text-sm">
                <p class="font-medium text-fg-inverse">Países</p>
                <ul class="mt-2 space-y-1">
                    @foreach (App\Models\Country::where('status', App\Domain\Geo\Enums\CountryStatus::Active)->orderBy('name')->get(['name', 'slug']) as $footerCountry)
                        <li><a href="/{{ $footerCountry->slug }}" class="text-fg-inverse/80 hover:text-fg-inverse">{{ $footerCountry->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </footer>
</body>
</html>

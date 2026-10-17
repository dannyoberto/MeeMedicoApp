{{--
    Cabecera en banda oscura de marca de las páginas interiores (listado, especialidad,
    búsqueda, ficha). Da identidad sin fotografía: textura de puntos, titular grande y
    cifras en oro. $overlap deja espacio abajo para que una tarjeta se monte encima.
--}}
@props(['breadcrumbs' => [], 'overlap' => false])
<section {{ $attributes->class(['band-dark', 'pb-24 md:pb-28' => $overlap, 'pb-10 md:pb-14' => ! $overlap]) }}>
    <div class="mx-auto max-w-content px-4 pt-6 md:pt-8">
        @if ($breadcrumbs !== [])
            <x-public.breadcrumbs :items="$breadcrumbs" :inverse="true" />
        @endif
        <div class="mt-4 md:mt-6">
            {{ $slot }}
        </div>
    </div>
</section>

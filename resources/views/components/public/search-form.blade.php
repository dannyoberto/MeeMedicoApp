{{--
    Buscador. Sin JavaScript es un <form method="get"> que lleva a /{pais}/buscar, y
    funciona entero. Con JavaScript, la isla SearchAutocomplete lo sustituye por la misma
    marca más sugerencias (design-system §7.3): mismas clases, sin salto de layout.

    $enhance = false lo deja como formulario simple. Lo usa la cabecera: si fuera isla,
    toda página con cabecera (la ficha incluida) descargaría React.
--}}
@props([
    'country',
    'value' => null,
    'size' => 'lg',
    'id' => 'q',
    'autofocus' => false,
    'enhance' => true,
])
@php
    $props = [
        'country' => $country->slug,
        'action' => "/{$country->slug}/buscar",
        'value' => $value,
        'size' => $size,
        'inputId' => $id,
        'placeholder' => 'Especialidad, ciudad o nombre',
    ];
@endphp
<div class="island" style="--island-min-h: {{ $size === 'lg' ? '3.5rem' : '2.75rem' }}" @if ($enhance) data-island="SearchAutocomplete" data-island-load="interaction" data-props='@json($props)' @endif>
    <form action="{{ $props['action'] }}" method="get" role="search" class="search-form search-form-{{ $size }}">
        <div class="search-form-field">
            <label for="{{ $id }}" class="sr-only">Buscar médicos</label>
            <x-heroicon-o-magnifying-glass class="search-form-icon" aria-hidden="true" />
            <input
                id="{{ $id }}"
                name="q"
                type="search"
                value="{{ $value }}"
                class="input"
                placeholder="{{ $props['placeholder'] }}"
                autocomplete="off"
                enterkeyhint="search"
                maxlength="200"
                @if ($autofocus) autofocus @endif
            >
        </div>
        <button type="submit" class="btn btn-primary">
            <x-heroicon-o-magnifying-glass class="size-5 sm:hidden" aria-hidden="true" />
            <span class="sr-only sm:not-sr-only">Buscar</span>
        </button>
    </form>
</div>

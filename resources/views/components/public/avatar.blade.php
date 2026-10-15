{{--
    Foto del médico o, si no hay, sus iniciales generadas aquí. Nunca un servicio externo
    de avatares: le enviaría el nombre de cada médico (design-system §12.1).
    $eager solo para la imagen principal de la página (LCP).
--}}
@props([
    'name',
    'photo' => null,
    'size' => 56,
    'eager' => false,
])
@php
    $initials = collect(preg_split('/\s+/u', trim(preg_replace('/^(dra?|lic)\.?\s+/iu', '', $name))))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->join('');
@endphp
@if ($photo)
    <img
        src="{{ $photo }}"
        alt=""
        width="{{ $size }}"
        height="{{ $size }}"
        style="--avatar-size: {{ $size }}px"
        loading="{{ $eager ? 'eager' : 'lazy' }}"
        @if ($eager) fetchpriority="high" @endif
        decoding="async"
        {{ $attributes->class('avatar') }}
    >
@else
    <span style="--avatar-size: {{ $size }}px" aria-hidden="true" {{ $attributes->class('avatar avatar-initials') }}>{{ $initials }}</span>
@endif

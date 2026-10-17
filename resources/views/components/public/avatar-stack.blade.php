{{--
    Fila de caras + cifra real de médicos publicados. Las fotos son de ilustración
    (Unsplash): no representan a médicos concretos del directorio.
    $inverse: sobre banda oscura (.band-dark).
--}}
@props(['total', 'place', 'inverse' => false])
<div {{ $attributes->class('flex items-center gap-3') }}>
    <div @class(['avatar-stack', '[&>*]:border-surface-inverse' => $inverse]) aria-hidden="true">
        @foreach (['doctor-hero', 'doctor-scrubs', 'doctor-coat', 'doctor-senior'] as $photo)
            <img src="/images/home/{{ $photo }}-160.webp" alt="" width="40" height="40" class="avatar" style="--avatar-size: 2.5rem" loading="lazy">
        @endforeach
    </div>
    <p @class(['text-sm', 'text-muted-inverse' => $inverse, 'text-fg-muted' => ! $inverse])>
        <span @class(['font-semibold', 'text-fg-inverse' => $inverse, 'text-fg' => ! $inverse])>{{ number_format($total, 0, ',', '.') }} {{ $total === 1 ? 'médico' : 'médicos' }}</span>
        en {{ $place }}
    </p>
</div>

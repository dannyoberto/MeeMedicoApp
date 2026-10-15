{{--
    Fila de caras + cifra real de médicos publicados. Las fotos son de ilustración
    (Unsplash): no representan a médicos concretos del directorio.
--}}
@props(['total', 'place'])
<div {{ $attributes->class('flex items-center gap-3') }}>
    <div class="avatar-stack" aria-hidden="true">
        @foreach (['doctor-hero', 'doctor-scrubs', 'doctor-coat', 'doctor-senior'] as $photo)
            <img src="/images/home/{{ $photo }}-160.webp" alt="" width="40" height="40" class="avatar" style="--avatar-size: 2.5rem" loading="lazy">
        @endforeach
    </div>
    <p class="text-sm text-fg-muted">
        <span class="font-semibold text-fg">{{ number_format($total, 0, ',', '.') }} {{ $total === 1 ? 'médico' : 'médicos' }}</span>
        en {{ $place }}
    </p>
</div>

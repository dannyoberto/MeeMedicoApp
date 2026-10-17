{{--
    Tarjeta de resultado. Requiere las relaciones de PostgresDoctorSearch::cardRelations().
    Toda la tarjeta lleva a la ficha; los botones de contacto quedan por encima y llaman
    directo. Foto y nombre llevan view-transition-name: al abrir la ficha "vuelan" a su
    sitio (View Transitions entre documentos, components.css).
--}}
@props([
    'doctor',
    'source' => 'listing',
    'headingLevel' => 'h2',
])
@php
    use App\Domain\Directory\Enums\FacilityStatus;
    use App\Domain\Directory\Enums\VerificationStatus;
    use App\Http\Support\ContactLink;
    use App\Http\Support\PublicUrl;

    $url = PublicUrl::doctor($doctor);
    $name = $doctor->displayName();
    $location = $doctor->locations->first();
    // El establecimiento si es público; si no, el nombre del consultorio, si lo tiene.
    // Sin puntuación suelta al inicio: algunos imports traen ", Clínica X".
    $place = $location?->facility?->status === FacilityStatus::Active ? $location->facility->name : $location?->name;
    $place = $place === null ? null : (trim($place, " \t,.;-") ?: null);
    $primary = ContactLink::primary($doctor);
    $secondary = ContactLink::secondary($doctor);
    $vt = strtolower($doctor->id);
@endphp
<article {{ $attributes->class('card doctor-card flex flex-col') }}>
    <div class="flex gap-4">
        <span class="shrink-0" style="view-transition-name: photo-{{ $vt }}">
            <x-public.avatar :name="$name" :photo="PublicUrl::photo($doctor)" :size="64" />
        </span>
        <div class="min-w-0 flex-1">
            <{{ $headingLevel }} class="text-lg font-bold leading-snug tracking-tight">
                <a href="{{ $url }}" class="doctor-card-link" style="view-transition-name: name-{{ $vt }}">{{ $name }}</a>
            </{{ $headingLevel }}>
            <p class="mt-0.5 text-sm font-medium text-primary">{{ $doctor->specialties->take(2)->pluck('name')->join(' · ') }}</p>
            @if ($doctor->verification_status === VerificationStatus::Verified)
                <x-public.verified-badge class="mt-2" />
            @endif
        </div>
    </div>

    @if ($location?->city)
        <p class="mt-4 flex items-start gap-1.5 text-sm text-fg-muted">
            <x-heroicon-m-map-pin class="mt-0.5 size-4 shrink-0 text-fg-subtle" aria-hidden="true" />
            <span class="min-w-0">{{ $location->city->name }}@if ($place) · {{ $place }}@endif</span>
        </p>
    @endif

    @if ($doctor->profile?->headline)
        <p class="mt-2 line-clamp-2 text-sm text-fg-muted">{{ $doctor->profile->headline }}</p>
    @endif

    {{-- Empuja las acciones al fondo: en la rejilla, todas las tarjetas de una fila las alinean. --}}
    <div class="min-h-5 flex-1" aria-hidden="true"></div>
    <div class="doctor-card-actions flex flex-wrap items-center gap-2 border-t border-border pt-4">
        @if ($primary)
            <x-public.contact-link :link="$primary" :doctor="$doctor" :source="$source" class="btn-secondary btn-sm" />
        @endif
        @if ($secondary)
            <x-public.contact-link :link="$secondary" :doctor="$doctor" :source="$source" class="btn-secondary btn-sm" />
        @endif
        <a href="{{ $url }}" class="btn btn-ghost btn-sm ml-auto" tabindex="-1" aria-hidden="true">
            Ver perfil
            <x-heroicon-m-arrow-right class="size-4" />
        </a>
    </div>
</article>

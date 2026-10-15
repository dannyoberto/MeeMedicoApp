{{--
    Tarjeta de resultado. Requiere las relaciones de PostgresDoctorSearch::cardRelations().
    Toda la tarjeta lleva a la ficha; el botón de contacto queda por encima y llama directo.
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
    $place = $location?->facility?->status === FacilityStatus::Active ? $location->facility->name : $location?->name;
    $contact = ContactLink::primary($doctor);
@endphp
<article {{ $attributes->class('card card-interactive doctor-card flex flex-col') }}>
    <div class="flex gap-4">
        <x-public.avatar :name="$name" :photo="PublicUrl::photo($doctor)" :size="56" />
        <div class="min-w-0 flex-1">
            <{{ $headingLevel }} class="text-lg font-semibold">
                <a href="{{ $url }}" class="doctor-card-link">{{ $name }}</a>
            </{{ $headingLevel }}>
            <p class="text-sm text-fg-muted">{{ $doctor->specialties->take(2)->pluck('name')->join(' · ') }}</p>
            @if ($doctor->verification_status === VerificationStatus::Verified)
                <x-public.verified-badge class="mt-2" />
            @endif
        </div>
    </div>

    @if ($location?->city)
        <p class="mt-3 flex items-start gap-1.5 text-sm text-fg-muted">
            <x-heroicon-m-map-pin class="mt-0.5 size-4 shrink-0 text-fg-subtle" aria-hidden="true" />
            <span class="min-w-0">{{ $location->city->name }}@if ($place) · {{ $place }}@endif</span>
        </p>
    @endif

    @if ($doctor->profile?->headline)
        <p class="mt-2 line-clamp-2 text-sm text-fg-muted">{{ $doctor->profile->headline }}</p>
    @endif

    <div class="doctor-card-actions mt-4 flex flex-wrap gap-2 pt-1 md:mt-auto">
        @if ($contact)
            <x-public.contact-link :link="$contact" :doctor="$doctor" :source="$source" class="btn-secondary btn-sm" />
        @endif
        <a href="{{ $url }}" class="btn btn-ghost btn-sm" tabindex="-1" aria-hidden="true">Ver perfil</a>
    </div>
</article>

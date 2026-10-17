@php
    use App\Domain\Directory\Enums\ContactEventType;
    use App\Domain\Directory\Enums\FacilityStatus;
@endphp
<x-layouts.public :country="$country" :seo="$seo" :json-ld="$jsonLd" :contact-bar="$primaryContact !== null">
    @php $vt = strtolower($doctor->id); @endphp
    <x-public.page-hero :breadcrumbs="$breadcrumbs" :overlap="true">
        <p class="eyebrow"><span class="eyebrow-dot"></span>Perfil profesional{{ $primaryLocation?->city ? ' · '.$primaryLocation->city->name : '' }}</p>
    </x-public.page-hero>

    <div class="mx-auto -mt-20 max-w-content px-4 pb-10 md:-mt-24">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-8">
            {{-- Cabecera: tarjeta montada sobre la banda --}}
            <header class="card relative shadow-md lg:col-start-1 md:p-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                    <span class="shrink-0" style="view-transition-name: photo-{{ $vt }}">
                        <x-public.avatar :name="$name" :photo="$photoUrl" :size="120" :eager="true" />
                    </span>
                    <div class="min-w-0">
                        <h1 class="text-3xl font-extrabold tracking-tight md:text-4xl">
                            <span style="view-transition-name: name-{{ $vt }}">{{ $name }}</span>
                        </h1>
                        @if ($doctor->specialties->isNotEmpty())
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach ($doctor->specialties as $specialty)
                                    <li @class(['badge text-sm', 'badge-primary' => $loop->first, 'badge-gray' => ! $loop->first])>{{ $specialty->name }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-fg-muted">
                            @if ($verified)
                                <x-public.verified-badge />
                            @endif
                            @if ($primaryLocation?->city)
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-m-map-pin class="size-4 shrink-0 text-fg-subtle" aria-hidden="true" />
                                    {{ $primaryLocation->city->name }}, {{ $country->name }}
                                </span>
                            @endif
                            @if ($doctor->languages->isNotEmpty())
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-m-language class="size-4 shrink-0 text-fg-subtle" aria-hidden="true" />
                                    {{ $doctor->languages->pluck('name')->join(', ') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                @if ($doctor->profile?->headline)
                    <p class="mt-6 max-w-prose border-t border-border pt-5 text-lg">{{ $doctor->profile->headline }}</p>
                @endif
            </header>

            {{-- Contacto: después de la cabecera en móvil, columna lateral fija en escritorio --}}
            <aside class="lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-labelledby="contacto">
                <div class="card border-t-4 border-t-primary shadow-md lg:sticky lg:top-6">
                    <h2 id="contacto" class="text-xl">Contactar</h2>
                    <p class="mt-1 text-sm text-fg-muted">Habla directo con el consultorio.</p>
                    @if ($contacts->isNotEmpty())
                        <div class="mt-4 flex flex-col gap-2">
                            @foreach ($contacts as $contact)
                                <x-public.contact-link
                                    :link="$contact"
                                    :doctor="$doctor"
                                    :show-value="true"
                                    @class(['btn-block justify-start', 'btn-primary' => $loop->first, 'btn-secondary' => ! $loop->first])
                                />
                            @endforeach
                        </div>
                        <p class="mt-4 text-xs text-fg-subtle">
                            MeeMedico no gestiona citas: hablas directo con el consultorio.
                        </p>
                    @else
                        <p class="mt-2 text-sm text-fg-muted">Este perfil no tiene contactos públicos.</p>
                    @endif
                </div>
            </aside>

            {{-- Detalle --}}
            <div class="space-y-6 lg:col-start-1">
                @if ($doctor->locations->isNotEmpty())
                    <section class="card" aria-labelledby="donde-atiende">
                        <h2 id="donde-atiende" class="text-xl">Dónde atiende</h2>
                        <ul class="mt-4 divide-y divide-border">
                            @foreach ($doctor->locations as $location)
                                @php
                                    $facility = $location->facility?->status === FacilityStatus::Active ? $location->facility : null;
                                    $place = collect([$location->address, $location->address_2, $location->city?->name, $location->city?->region?->name])->filter()->join(', ');
                                    $mapsQuery = $location->latitude && $location->longitude
                                        ? "{$location->latitude},{$location->longitude}"
                                        : collect([$facility?->name, $place, $country->name])->filter()->join(', ');
                                @endphp
                                <li class="flex flex-col gap-4 py-5 first:pt-0 last:pb-0 sm:flex-row sm:items-start">
                                    <span class="specialty-tile-icon hidden shrink-0 sm:inline-flex">
                                        <x-dynamic-component :component="$facility ? 'heroicon-o-building-office-2' : 'heroicon-o-map-pin'" class="size-6" aria-hidden="true" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        @if ($facility)
                                            <p class="font-medium">Atiende en {{ $facility->name }}</p>
                                        @elseif ($location->name)
                                            <p class="font-medium">{{ $location->name }}</p>
                                        @endif
                                        <p class="text-fg-muted">{{ $place }}</p>
                                        @if ($loop->first && $doctor->locations->count() > 1)
                                            <span class="badge badge-primary mt-2">Consulta principal</span>
                                        @endif
                                    </div>
                                    <a
                                        href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($mapsQuery) }}"
                                        target="_blank"
                                        rel="noopener nofollow"
                                        class="btn btn-secondary btn-sm shrink-0 self-start"
                                        data-contact="{{ ContactEventType::Directions->value }}"
                                        data-doctor="{{ $doctor->id }}"
                                        data-source="profile"
                                    >
                                        <x-heroicon-o-map-pin class="size-5" aria-hidden="true" />
                                        Cómo llegar
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @foreach (['bio' => "Sobre {$name}", 'education' => 'Formación', 'experience' => 'Experiencia'] as $field => $heading)
                    @if (filled($doctor->profile?->{$field}))
                        <section class="card" aria-labelledby="sec-{{ $field }}">
                            <h2 id="sec-{{ $field }}" class="text-xl">{{ $heading }}</h2>
                            <div class="mt-3 max-w-prose space-y-3">
                                @foreach (preg_split('/\R{2,}/', trim($doctor->profile->{$field})) as $paragraph)
                                    <p>{!! nl2br(e($paragraph)) !!}</p>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach

                @if ($listingUrl)
                    <section class="band-dark flex flex-col gap-5 rounded-[1.5rem] p-6 sm:flex-row sm:items-center sm:justify-between md:p-8" aria-labelledby="mas-especialistas">
                        <div>
                            <h2 id="mas-especialistas" class="text-2xl">{{ $primarySpecialty->name }} en {{ $primaryLocation->city->name }}</h2>
                            <p class="text-muted-inverse mt-1">
                                @if ($listingTotal > 1)
                                    <span class="font-bold text-brand">{{ $listingTotal }}</span> especialistas publicados en la ciudad.
                                @else
                                    Compara con especialistas de otras ciudades.
                                @endif
                            </p>
                        </div>
                        <a href="{{ $listingUrl }}" class="btn btn-inverse shrink-0">
                            Ver especialistas
                            <x-heroicon-m-arrow-right class="size-5" aria-hidden="true" />
                        </a>
                    </section>
                @endif

                <section class="rounded-lg border border-border bg-surface-muted p-4 text-sm md:p-6" aria-labelledby="eres-este-medico">
                    <h2 id="eres-este-medico" class="text-base">¿Eres {{ $name }}?</h2>
                    <p class="mt-1 text-fg-muted">Puedes pedirnos corregir tus datos o salir del directorio.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ $operations['email'] }}" class="btn btn-secondary btn-sm">
                            <x-heroicon-o-envelope class="size-5" aria-hidden="true" />
                            Escribir por correo
                        </a>
                        @if ($operations['whatsapp'])
                            <a href="{{ $operations['whatsapp'] }}" target="_blank" rel="noopener nofollow" class="btn btn-secondary btn-sm">
                                <x-heroicon-o-chat-bubble-left-right class="size-5" aria-hidden="true" />
                                Escribir por WhatsApp
                            </a>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>

    <x-public.contact-bar :doctor="$doctor" :primary="$primaryContact" :secondary="$secondaryContact" />
</x-layouts.public>

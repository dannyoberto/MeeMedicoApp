@php
    use App\Domain\Directory\Enums\ContactEventType;
    use App\Domain\Directory\Enums\FacilityStatus;
@endphp
<x-layouts.public :country="$country" :seo="$seo" :json-ld="$jsonLd" :contact-bar="$primaryContact !== null">
    <div class="mx-auto max-w-content px-4 py-8 md:py-10">
        <x-public.breadcrumbs :items="$breadcrumbs" />

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-8">
            {{-- Cabecera --}}
            <header class="card lg:col-start-1">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                    <x-public.avatar :name="$name" :photo="$photoUrl" :size="96" :eager="true" />
                    <div class="min-w-0">
                        <h1 class="text-2xl md:text-3xl">{{ $name }}</h1>
                        @if ($doctor->specialties->isNotEmpty())
                            <p class="mt-1 text-lg text-fg-muted">{{ $doctor->specialties->pluck('name')->join(' · ') }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($verified)
                                <x-public.verified-badge />
                            @endif
                            @if ($primaryLocation?->city)
                                <span class="badge badge-gray">
                                    <x-heroicon-m-map-pin class="size-4 shrink-0" aria-hidden="true" />
                                    {{ $primaryLocation->city->name }}
                                </span>
                            @endif
                        </div>
                        @if ($doctor->profile?->headline)
                            <p class="mt-4 max-w-prose">{{ $doctor->profile->headline }}</p>
                        @endif
                    </div>
                </div>
            </header>

            {{-- Contacto: después de la cabecera en móvil, columna lateral fija en escritorio --}}
            <aside class="lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-labelledby="contacto">
                <div class="card lg:sticky lg:top-6">
                    <h2 id="contacto" class="text-lg">Contactar</h2>
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
                                <li class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
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
                                        class="btn btn-ghost btn-sm shrink-0 self-start"
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

                @if ($doctor->languages->isNotEmpty())
                    <section class="card" aria-labelledby="idiomas">
                        <h2 id="idiomas" class="text-xl">Idiomas</h2>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($doctor->languages as $language)
                                <li class="badge badge-gray">{{ $language->name }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($listingUrl)
                    <a href="{{ $listingUrl }}" class="btn btn-ghost justify-start text-left">
                        <x-heroicon-m-arrow-left class="size-5" aria-hidden="true" />
                        Más {{ mb_strtolower($primarySpecialty->name) }} en {{ $primaryLocation->city->name }}
                    </a>
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

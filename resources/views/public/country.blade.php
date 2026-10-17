@php
    use App\Domain\Search\SearchCriteria;
    use App\Http\Support\PublicUrl;
    use App\Http\Support\SpecialtyIcon;

    $tiles = $specialties->take(8);
    $more = $specialties->slice(8);
@endphp
<x-layouts.public :country="$country" :seo="$seo" :header-search="false">
    {{-- Hero: banda oscura de marca, buscador protagonista y fotografía --}}
    <section class="band-dark overflow-hidden">
        <div class="mx-auto grid max-w-content items-center gap-12 px-4 pt-10 pb-16 md:pt-16 lg:grid-cols-[1.15fr_0.85fr] lg:gap-16 lg:pt-20 lg:pb-24">
            <div class="min-w-0">
                <p class="eyebrow"><span class="eyebrow-dot"></span>Directorio médico de {{ $country->name }}</p>

                <h1 class="hero-title mt-6">
                    Tu próximo médico está a <span class="hero-highlight">una llamada</span>
                </h1>
                <p class="text-muted-inverse mt-5 max-w-xl text-lg">
                    Escribe como lo dirías —especialidad, ciudad, idioma o nombre— y contacta directo al consultorio
                    por teléfono o WhatsApp. Sin registros ni intermediarios.
                </p>

                <div class="mt-8 max-w-2xl">
                    <x-public.search-form :country="$country" />
                </div>

                @if ($examples !== [])
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <span class="text-muted-inverse text-sm">Prueba con</span>
                        @foreach ($examples as $example)
                            <a href="{{ $example['url'] }}" class="chip">
                                <x-heroicon-m-magnifying-glass class="size-4 opacity-70" aria-hidden="true" />
                                {{ $example['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($total > 0)
                    <x-public.avatar-stack class="mt-8" :total="$total" :place="$country->name" :inverse="true" />
                @endif

                <x-public.hero-media-mobile class="mt-10 lg:hidden" />
            </div>

            <x-public.hero-media class="mx-auto hidden w-full max-w-md lg:block" :total="$total" :place="$country->name" />
        </div>
    </section>

    @if ($total === 0)
        <div class="mx-auto max-w-content px-4 py-10">
            <div class="alert alert-info">
                <x-heroicon-o-information-circle class="size-5 shrink-0" aria-hidden="true" />
                <p>Todavía no hay médicos publicados en {{ $country->name }}. Estamos cargando el directorio.</p>
            </div>
        </div>
    @else
        {{-- Especialidades --}}
        <section class="reveal mx-auto max-w-content px-4 py-16 md:py-24" aria-labelledby="especialidades">
            <p class="eyebrow"><span class="eyebrow-dot"></span>{{ $specialties->count() }} especialidades</p>
            <h2 id="especialidades" class="section-title mt-4">Especialidades más buscadas</h2>
            <p class="mt-3 max-w-prose text-lg text-fg-muted">Elige una y te mostramos en qué ciudades hay especialistas.</p>

            <ul class="mt-10 grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 lg:grid-cols-4">
                @foreach ($tiles as $item)
                    <li>
                        <a href="{{ PublicUrl::specialty($country, $item['specialty']) }}" @class(['specialty-tile h-full', 'specialty-tile-gold' => $loop->index % 3 === 1])>
                            <span class="specialty-tile-icon">
                                <x-dynamic-component :component="SpecialtyIcon::for($item['specialty']->slug)" class="size-6" aria-hidden="true" />
                            </span>
                            <span class="mt-auto">
                                <span class="block text-lg font-semibold leading-snug">{{ $item['specialty']->name }}</span>
                                <span class="mt-1 block text-sm text-fg-muted">{{ $item['total'] }} {{ $item['total'] === 1 ? 'médico' : 'médicos' }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($more->isNotEmpty())
                <details class="disclosure mt-6 border-0">
                    <summary class="text-fg-link">Ver las {{ $specialties->count() }} especialidades</summary>
                    <ul class="flex flex-wrap gap-2 pb-2">
                        @foreach ($more as $item)
                            <li>
                                <a href="{{ PublicUrl::specialty($country, $item['specialty']) }}" class="chip">
                                    {{ $item['specialty']->name }} <span class="chip-count">{{ $item['total'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>

        {{-- Cómo funciona --}}
        <section class="bg-surface" aria-labelledby="como-funciona">
            <div class="reveal mx-auto max-w-content px-4 py-16 md:py-24">
                <h2 id="como-funciona" class="section-title text-center">Así de fácil</h2>
                <p class="mx-auto mt-3 max-w-prose text-center text-lg text-fg-muted">Sin cuentas, sin formularios, sin esperar a que te devuelvan la llamada.</p>

                <ol class="mt-14 grid gap-10 md:grid-cols-3 md:gap-8">
                    @foreach ([
                        ['Busca', 'Escribe lo que necesitas: "pediatra en tu ciudad" o el nombre del médico.', 'magnifying-glass'],
                        ['Compara', 'Mira especialidades, consultorios, idiomas y quién tiene la licencia verificada.', 'user-group'],
                        ['Contacta', 'Llama o escribe por WhatsApp directo al consultorio con un toque.', 'phone'],
                    ] as $i => [$title, $text, $icon])
                        <li class="flex flex-col items-center text-center">
                            <span class="step-number">{{ $i + 1 }}</span>
                            <h3 class="mt-5 flex items-center gap-2 text-xl font-bold">
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 text-primary" aria-hidden="true" />
                                {{ $title }}
                            </h3>
                            <p class="mt-2 max-w-xs text-fg-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Médicos destacados: datos reales del directorio --}}
        @if ($featured->isNotEmpty())
            <section class="reveal mx-auto max-w-content px-4 py-16 md:py-24" aria-labelledby="medicos">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 id="medicos" class="section-title">Conoce a algunos médicos</h2>
                        <p class="mt-3 text-lg text-fg-muted">Perfiles publicados en {{ $country->name }}, con su contacto directo.</p>
                    </div>
                </div>
                <div class="mt-10 grid gap-4 md:grid-cols-2">
                    @foreach ($featured as $doctor)
                        <x-public.doctor-card :doctor="$doctor" heading-level="h3" source="listing" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Ciudades --}}
        <section class="reveal mx-auto max-w-content px-4 pb-16 md:pb-24" aria-labelledby="ciudades">
            <h2 id="ciudades" class="text-2xl md:text-3xl">Busca en tu ciudad</h2>
            <ul class="mt-6 flex flex-wrap gap-2">
                @foreach ($cities as $item)
                    <li>
                        <a href="{{ PublicUrl::forCriteria(new SearchCriteria($country, city: $item['city'])) }}" class="chip">
                            <x-heroicon-m-map-pin class="size-4 text-fg-subtle" aria-hidden="true" />
                            {{ $item['city']->name }} <span class="chip-count">{{ $item['total'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-public.join-doctors class="reveal" :country="$country" />
</x-layouts.public>

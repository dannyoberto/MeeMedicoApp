@php
    use App\Http\Support\PublicUrl;
    use App\Http\Support\SpecialtyIcon;

    $total = $doctors->total();
    // Filtros como chips (enlaces GET): sin JavaScript y sin botón "Aplicar".
    $filterUrl = fn (array $query) => PublicUrl::listing($country, $specialty, $city, array_merge([
        'sub' => $criteria->subspecialty?->slug,
        'idioma' => $criteria->language?->code,
    ], $query));
@endphp
<x-layouts.public :country="$country" :seo="$seo" :json-ld="$jsonLd">
    <x-public.page-hero :breadcrumbs="$breadcrumbs">
        <div class="flex items-start gap-4 md:gap-6">
            <span class="hidden size-16 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-brand sm:inline-flex">
                <x-dynamic-component :component="SpecialtyIcon::for($specialty->slug)" class="size-8" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h1 class="text-4xl md:text-5xl">{{ $specialty->name }} en {{ $city->name }}</h1>
                <p class="text-muted-inverse mt-3 flex flex-wrap items-baseline gap-x-2 text-lg">
                    @if ($total > 0)
                        <span class="stat-number">{{ $total }}</span>
                        <span>
                            {{ $total === 1 ? 'médico' : 'médicos' }}
                            @if ($criteria->subspecialty) en {{ mb_strtolower($criteria->subspecialty->name) }} @endif
                            @if ($criteria->language) que hablan {{ mb_strtolower($criteria->language->name) }} @endif
                            · {{ $city->name }}, {{ $country->name }}
                        </span>
                    @else
                        Sin resultados con estos criterios.
                    @endif
                </p>
            </div>
        </div>

        @if ($subspecialties->isNotEmpty() || $languages->isNotEmpty())
            <div class="mt-8 space-y-3" aria-label="Filtrar resultados">
                @if ($subspecialties->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-muted-inverse mr-1 text-sm">Subespecialidad</span>
                        <a href="{{ $filterUrl(['sub' => null]) }}" @class(['chip', 'chip-active' => ! $criteria->subspecialty])>Todas</a>
                        @foreach ($subspecialties as $sub)
                            <a href="{{ $filterUrl(['sub' => $sub->slug]) }}" @class(['chip', 'chip-active' => $criteria->subspecialty?->is($sub)])>{{ $sub->name }}</a>
                        @endforeach
                    </div>
                @endif
                @if ($languages->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-muted-inverse mr-1 text-sm">Habla también</span>
                        <a href="{{ $filterUrl(['idioma' => null]) }}" @class(['chip', 'chip-active' => ! $criteria->language])>Cualquier idioma</a>
                        @foreach ($languages as $language)
                            <a href="{{ $filterUrl(['idioma' => $language->code]) }}" @class(['chip', 'chip-active' => $criteria->language?->is($language)])>{{ $language->name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </x-public.page-hero>

    <div class="mx-auto max-w-content px-4 py-10 md:py-12">
        @if ($doctors->isNotEmpty())
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($doctors as $doctor)
                    <x-public.doctor-card :doctor="$doctor" />
                @endforeach
            </div>

            <x-public.pagination
                :paginator="$doctors"
                :url="fn (int $page) => PublicUrl::forCriteria($criteria->withPage($page))"
            />
        @else
            <div class="card text-center md:py-12">
                <span class="specialty-tile-icon mx-auto">
                    <x-heroicon-o-magnifying-glass class="size-6" aria-hidden="true" />
                </span>
                <h2 class="mt-4 text-2xl">No encontramos {{ mb_strtolower($specialty->name) }} en {{ $city->name }}{{ $filtered ? ' con esos filtros' : '' }}</h2>
                @if ($filtered)
                    <p class="mt-2 text-fg-muted">Prueba sin filtros para ver a todos los especialistas de la ciudad.</p>
                    <a href="{{ $baseUrl }}" class="btn btn-primary mt-6">Ver todos en {{ $city->name }}</a>
                @elseif ($otherCities->isEmpty())
                    <p class="mt-2 text-fg-muted">Todavía no hay especialistas publicados en {{ $country->name }}.</p>
                    <a href="{{ PublicUrl::country($country) }}" class="btn btn-secondary mt-6">Ver otras especialidades</a>
                @else
                    <p class="mt-2 text-fg-muted">Mira en otras ciudades de {{ $country->name }}, aquí abajo.</p>
                @endif
            </div>
        @endif

        @if ($otherCities->isNotEmpty())
            <section class="mt-14" aria-labelledby="otras-ciudades">
                <h2 id="otras-ciudades" class="text-2xl">{{ $specialty->name }} en otras ciudades</h2>
                <ul class="mt-5 flex flex-wrap gap-2">
                    @foreach ($otherCities as $item)
                        <li>
                            <a href="{{ PublicUrl::listing($country, $specialty, $item['city']) }}" class="chip">
                                <x-heroicon-m-map-pin class="size-4 text-fg-subtle" aria-hidden="true" />
                                {{ $item['city']->name }} <span class="chip-count">{{ $item['total'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.public>

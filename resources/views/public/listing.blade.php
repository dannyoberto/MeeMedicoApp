@php use App\Http\Support\PublicUrl; @endphp
<x-layouts.public :country="$country" :seo="$seo" :json-ld="$jsonLd">
    <div class="mx-auto max-w-content px-4 py-8 md:py-10">
        <x-public.breadcrumbs :items="$breadcrumbs" />

        <header class="mt-4">
            <h1 class="text-3xl md:text-4xl">{{ $specialty->name }} en {{ $city->name }}</h1>
            <p class="mt-2 text-fg-muted">
                @if ($doctors->total() > 0)
                    {{ $doctors->total() }} {{ $doctors->total() === 1 ? 'médico' : 'médicos' }}
                    @if ($criteria->subspecialty) en {{ mb_strtolower($criteria->subspecialty->name) }} @endif
                    @if ($criteria->language) que hablan {{ mb_strtolower($criteria->language->name) }} @endif
                    · {{ $city->name }}, {{ $country->name }}
                @else
                    Sin resultados con estos criterios.
                @endif
            </p>
        </header>

        @if ($subspecialties->isNotEmpty() || $languages->isNotEmpty())
            <form method="get" action="{{ $baseUrl }}" class="mt-6 flex flex-wrap items-end gap-3" aria-label="Filtrar resultados">
                @if ($subspecialties->isNotEmpty())
                    <div class="field min-w-48 flex-1 sm:flex-none">
                        <label for="sub" class="field-label">Subespecialidad</label>
                        <select id="sub" name="sub" class="input">
                            <option value="">Todas</option>
                            @foreach ($subspecialties as $sub)
                                <option value="{{ $sub->slug }}" @selected($criteria->subspecialty?->is($sub))>{{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($languages->isNotEmpty())
                    <div class="field min-w-48 flex-1 sm:flex-none">
                        <label for="idioma" class="field-label">Habla también</label>
                        <select id="idioma" name="idioma" class="input">
                            <option value="">Cualquier idioma</option>
                            @foreach ($languages as $language)
                                <option value="{{ $language->code }}" @selected($criteria->language?->is($language))>{{ $language->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="btn btn-secondary">Aplicar</button>
                @if ($filtered)
                    <a href="{{ $baseUrl }}" class="btn btn-ghost">Quitar filtros</a>
                @endif
            </form>
        @endif

        @if ($doctors->isNotEmpty())
            <div class="mt-8 grid gap-4 lg:grid-cols-2">
                @foreach ($doctors as $doctor)
                    <x-public.doctor-card :doctor="$doctor" />
                @endforeach
            </div>

            <x-public.pagination
                :paginator="$doctors"
                :url="fn (int $page) => PublicUrl::forCriteria($criteria->withPage($page))"
            />
        @else
            <div class="mt-8 card">
                <h2 class="text-xl">No encontramos {{ mb_strtolower($specialty->name) }} en {{ $city->name }}{{ $filtered ? ' con esos filtros' : '' }}</h2>
                @if ($filtered)
                    <p class="mt-2 text-fg-muted">Prueba sin filtros para ver a todos los especialistas de la ciudad.</p>
                    <a href="{{ $baseUrl }}" class="btn btn-primary mt-4">Ver todos en {{ $city->name }}</a>
                @elseif ($otherCities->isNotEmpty())
                    <p class="mt-2 text-fg-muted">Hay especialistas en otras ciudades de {{ $country->name }}:</p>
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ($otherCities as $item)
                            <li>
                                <a href="{{ PublicUrl::listing($country, $specialty, $item['city']) }}" class="chip">
                                    {{ $item['city']->name }} <span class="chip-count">{{ $item['total'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-fg-muted">Todavía no hay especialistas publicados en {{ $country->name }}.</p>
                    <a href="{{ PublicUrl::country($country) }}" class="btn btn-secondary mt-4">Ver otras especialidades</a>
                @endif
            </div>
        @endif
    </div>
</x-layouts.public>

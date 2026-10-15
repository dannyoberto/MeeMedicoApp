@php use App\Http\Support\PublicUrl; @endphp
<x-layouts.public :country="$country" :seo="$seo" :header-search="false">
    <div class="mx-auto max-w-content px-4 py-8 md:py-10">
        <x-public.breadcrumbs :items="$breadcrumbs" />

        <h1 class="mt-4 text-3xl md:text-4xl">
            @if ($query !== null)
                Resultados para «{{ $query }}»
            @else
                Buscar médicos en {{ $country->name }}
            @endif
        </h1>

        <div class="mt-6 max-w-2xl">
            <x-public.search-form :country="$country" :value="$query" :autofocus="$query === null && $chips === []" />
        </div>

        @if ($chips !== [])
            <div class="mt-5 flex flex-wrap items-center gap-2" aria-label="Lo que entendimos de tu búsqueda">
                <span class="text-sm text-fg-muted">Buscando</span>
                @foreach ($chips as $chip)
                    <a href="{{ $chip['url'] }}" class="chip chip-active" aria-label="Quitar {{ $chip['label'] }}">
                        {{ $chip['label'] }}
                        <x-heroicon-m-x-mark class="size-4 shrink-0" aria-hidden="true" />
                    </a>
                @endforeach
            </div>
        @endif

        @if ($doctors !== null && $doctors->isNotEmpty())
            <p class="mt-8 text-sm text-fg-muted" role="status">
                {{ $doctors->total() }} {{ $doctors->total() === 1 ? 'resultado' : 'resultados' }}
            </p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($doctors as $doctor)
                    <x-public.doctor-card :doctor="$doctor" />
                @endforeach
            </div>

            <x-public.pagination
                :paginator="$doctors"
                :url="fn (int $page) => PublicUrl::forCriteria($criteria->withPage($page))"
            />
        @elseif ($doctors !== null)
            <div class="mt-8 card" role="status">
                <h2 class="text-xl">No encontramos médicos con esa búsqueda</h2>
                <p class="mt-2 text-fg-muted">
                    Prueba a quitar alguno de los criterios de arriba, o busca solo por especialidad o ciudad.
                </p>
            </div>
        @endif

        @if ($specialties->isNotEmpty())
            <section class="mt-10" aria-labelledby="especialidades">
                <h2 id="especialidades" class="text-xl">Especialidades en {{ $country->name }}</h2>
                <ul class="mt-4 flex flex-wrap gap-2">
                    @foreach ($specialties as $item)
                        <li>
                            <a href="{{ PublicUrl::specialty($country, $item['specialty']) }}" class="chip">
                                {{ $item['specialty']->name }} <span class="chip-count">{{ $item['total'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.public>

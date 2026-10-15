@php use App\Http\Support\PublicUrl; @endphp
<x-layouts.public :country="$country" :seo="$seo" :json-ld="$jsonLd">
    <div class="mx-auto max-w-content px-4 py-8 md:py-10">
        <x-public.breadcrumbs :items="$breadcrumbs" />

        <h1 class="mt-4 text-3xl md:text-4xl">{{ $specialty->name }} en {{ $country->name }}</h1>
        <p class="mt-2 text-fg-muted">
            @if ($doctors->total() > 0)
                {{ $doctors->total() }} {{ $doctors->total() === 1 ? 'especialista publicado' : 'especialistas publicados' }}.
                Elige tu ciudad para ver a todos.
            @else
                Todavía no hay especialistas en {{ mb_strtolower($specialty->name) }} publicados en {{ $country->name }}.
            @endif
        </p>

        @if ($specialty->description)
            <p class="mt-4 max-w-prose">{{ $specialty->description }}</p>
        @endif

        @if ($cities->isNotEmpty())
            <section class="mt-10" aria-labelledby="por-ciudad">
                <h2 id="por-ciudad" class="text-2xl">Por ciudad</h2>
                <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($cities as $item)
                        <li>
                            <a href="{{ PublicUrl::listing($country, $specialty, $item['city']) }}" class="card card-interactive flex items-center justify-between gap-3 no-underline">
                                <span class="min-w-0">
                                    <span class="block font-medium text-fg">{{ $specialty->name }} en {{ $item['city']->name }}</span>
                                    <span class="block text-sm text-fg-muted">{{ $item['total'] }} {{ $item['total'] === 1 ? 'médico' : 'médicos' }}</span>
                                </span>
                                <x-heroicon-m-arrow-right class="size-5 shrink-0 text-fg-subtle" aria-hidden="true" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($doctors->isNotEmpty())
            <section class="mt-12" aria-labelledby="especialistas">
                <h2 id="especialistas" class="text-2xl">Algunos especialistas</h2>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    @foreach ($doctors as $doctor)
                        <x-public.doctor-card :doctor="$doctor" heading-level="h3" />
                    @endforeach
                </div>
            </section>
        @else
            <a href="{{ PublicUrl::country($country) }}" class="btn btn-secondary mt-8">Ver otras especialidades</a>
        @endif
    </div>
</x-layouts.public>

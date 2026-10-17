@php
    use App\Http\Support\PublicUrl;
    use App\Http\Support\SpecialtyIcon;

    $total = $doctors->total();
@endphp
<x-layouts.public :country="$country" :seo="$seo" :json-ld="$jsonLd">
    <x-public.page-hero :breadcrumbs="$breadcrumbs">
        <div class="flex items-start gap-4 md:gap-6">
            <span class="hidden size-16 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-brand sm:inline-flex">
                <x-dynamic-component :component="SpecialtyIcon::for($specialty->slug)" class="size-8" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h1 class="text-4xl md:text-5xl">{{ $specialty->name }} en {{ $country->name }}</h1>
                <p class="text-muted-inverse mt-3 flex flex-wrap items-baseline gap-x-2 text-lg">
                    @if ($total > 0)
                        <span class="stat-number">{{ $total }}</span>
                        <span>{{ $total === 1 ? 'especialista' : 'especialistas' }} en {{ $cities->count() }} {{ $cities->count() === 1 ? 'ciudad' : 'ciudades' }}</span>
                    @else
                        Todavía no hay especialistas en {{ mb_strtolower($specialty->name) }} publicados en {{ $country->name }}.
                    @endif
                </p>
                @if ($specialty->description)
                    <p class="text-muted-inverse mt-4 max-w-prose">{{ $specialty->description }}</p>
                @endif
            </div>
        </div>
    </x-public.page-hero>

    <div class="mx-auto max-w-content px-4 py-10 md:py-12">
        @if ($cities->isNotEmpty())
            <section aria-labelledby="por-ciudad">
                <h2 id="por-ciudad" class="text-2xl md:text-3xl">Elige tu ciudad</h2>
                <ul class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 lg:grid-cols-4">
                    @foreach ($cities as $item)
                        <li>
                            <a href="{{ PublicUrl::listing($country, $specialty, $item['city']) }}" class="specialty-tile h-full">
                                <span class="specialty-tile-icon">
                                    <x-heroicon-o-map-pin class="size-6" aria-hidden="true" />
                                </span>
                                <span class="mt-auto">
                                    <span class="block text-lg font-semibold leading-snug">{{ $item['city']->name }}</span>
                                    <span class="mt-1 block text-sm text-fg-muted">{{ $item['total'] }} {{ $item['total'] === 1 ? 'médico' : 'médicos' }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($doctors->isNotEmpty())
            <section class="reveal mt-14" aria-labelledby="especialistas">
                <h2 id="especialistas" class="text-2xl md:text-3xl">Algunos especialistas</h2>
                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    @foreach ($doctors as $doctor)
                        <x-public.doctor-card :doctor="$doctor" heading-level="h3" />
                    @endforeach
                </div>
            </section>
        @else
            <a href="{{ PublicUrl::country($country) }}" class="btn btn-secondary">Ver otras especialidades</a>
        @endif
    </div>
</x-layouts.public>

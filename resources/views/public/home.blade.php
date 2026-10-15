@php
    use App\Http\Support\PublicUrl;

    $total = $countries->sum('doctors_count');
@endphp
<x-layouts.public :seo="$seo">
    <section class="overflow-hidden bg-surface">
        <div class="mx-auto grid max-w-content items-center gap-12 px-4 pt-10 pb-14 md:pt-14 lg:grid-cols-[1.15fr_0.85fr] lg:gap-16 lg:pt-20 lg:pb-24">
            <div class="min-w-0">
                <p class="eyebrow"><span class="eyebrow-dot"></span>Directorio médico de Latinoamérica</p>

                <h1 class="hero-title mt-6">
                    Tu próximo médico está a <span class="hero-highlight">una llamada</span>
                </h1>
                <p class="mt-5 max-w-xl text-lg text-fg-muted">
                    Especialistas por ciudad, con su teléfono, WhatsApp y dirección. Elige tu país y encuentra a tu médico
                    en segundos, sin registros ni intermediarios.
                </p>

                @if ($countries->isEmpty())
                    <div class="alert alert-info mt-8">
                        <x-heroicon-o-information-circle class="size-5 shrink-0" aria-hidden="true" />
                        <p>Estamos preparando el directorio. Muy pronto podrás buscar médicos aquí.</p>
                    </div>
                @else
                    <h2 class="mt-10 text-sm font-medium text-fg-muted">Elige tu país</h2>
                    <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($countries as $item)
                            <li>
                                <a href="{{ PublicUrl::country($item) }}" class="specialty-tile min-h-0 flex-row items-center">
                                    <span class="specialty-tile-icon">
                                        <x-heroicon-o-map-pin class="size-6" aria-hidden="true" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-lg font-semibold">{{ $item->name }}</span>
                                        <span class="block text-sm text-fg-muted">
                                            {{ number_format($item->doctors_count, 0, ',', '.') }} {{ $item->doctors_count === 1 ? 'médico' : 'médicos' }}
                                        </span>
                                    </span>
                                    <x-heroicon-m-arrow-right class="size-5 shrink-0 text-fg-subtle" aria-hidden="true" />
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if ($total > 0)
                        <x-public.avatar-stack class="mt-8" :total="$total" :place="$countries->count() === 1 ? $countries->first()->name : 'el directorio'" />
                    @endif
                @endif

                <x-public.hero-media-mobile class="mt-8 lg:hidden" />
            </div>

            <x-public.hero-media class="mx-auto hidden w-full max-w-md lg:block" />
        </div>
    </section>

    <section class="mx-auto max-w-content px-4 py-14 md:py-20" aria-labelledby="por-que">
        <h2 id="por-que" class="text-center text-3xl md:text-4xl">Pensado para que llames hoy</h2>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            @foreach ([
                ['phone', 'Contacto directo', 'Llamas o escribes por WhatsApp al consultorio. Sin intermediarios ni comisiones.'],
                ['shield-check', 'Licencias verificadas', 'El escudo verde indica que validamos la licencia médica del profesional.'],
                ['heart', 'Gratis para pacientes', 'Sin cuentas ni formularios: busca, compara y contacta.'],
            ] as [$icon, $title, $text])
                <div class="card">
                    <span @class(['specialty-tile-icon', 'bg-success text-success-solid' => $icon === 'shield-check'])>
                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" aria-hidden="true" />
                    </span>
                    <h3 class="mt-4 text-xl">{{ $title }}</h3>
                    <p class="mt-2 text-fg-muted">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <x-public.join-doctors />
</x-layouts.public>

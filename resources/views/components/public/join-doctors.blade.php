{{--
    Sección para médicos con el anticipo de planes.

    Fase 1 no tiene suscripciones ni flujo de claim en la web (AGENTS.md §7): los CTA
    abren un correo al buzón de operación (config meemedico.operations). Cuando exista
    el claim, "Quiero aparecer" apuntará a él; cuando exista premium, "Avísame" a sus
    planes. El perfil destacado lleva oro + estrella: es premium (design-system §4.4).
--}}
@props(['country' => null])
@php
    $email = config('meemedico.operations.email');
    $where = $country ? " ({$country->name})" : '';
    $joinUrl = "mailto:{$email}?subject=".rawurlencode("Quiero aparecer en MeeMedico{$where}");
    $notifyUrl = "mailto:{$email}?subject=".rawurlencode("Avísenme del perfil destacado{$where}");
@endphp
<section {{ $attributes->class('mx-auto max-w-content px-4') }} aria-labelledby="para-medicos">
    <div class="overflow-hidden rounded-[2rem] bg-surface-inverse text-fg-inverse lg:grid lg:grid-cols-[1.1fr_0.9fr]">
        <div class="p-8 md:p-12">
            <p class="eyebrow bg-white/10 text-fg-inverse"><span class="eyebrow-dot"></span>Para médicos</p>
            <h2 id="para-medicos" class="mt-5 text-3xl text-fg-inverse md:text-4xl">Que tus pacientes te encuentren y te llamen directo</h2>
            <p class="mt-4 max-w-prose text-fg-inverse/80">
                MeeMedico es gratis para los pacientes. Tu perfil muestra tus especialidades, tus consultorios
                y tu WhatsApp, y aparece cuando alguien busca tu especialidad en tu ciudad.
            </p>

            <ul class="mt-6 space-y-3">
                @foreach ([
                    'Contacto a un toque: teléfono y WhatsApp, sin intermediarios',
                    'Apareces en las búsquedas de tu especialidad y tu ciudad',
                    'Escudo de verificación cuando validamos tu licencia',
                ] as $benefit)
                    <li class="flex gap-3">
                        <x-heroicon-m-check-circle class="mt-0.5 size-5 shrink-0 text-brand" aria-hidden="true" />
                        <span class="text-fg-inverse/90">{{ $benefit }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-white/15 bg-white/5 p-4">
                    <p class="font-semibold text-fg-inverse">Perfil gratuito</p>
                    <p class="mt-1 text-sm text-fg-inverse/75">Aparece en el directorio y mantén tus datos al día.</p>
                </div>
                <div class="rounded-xl border border-premium-line/40 bg-white/5 p-4">
                    <p class="flex flex-wrap items-center gap-2 font-semibold text-fg-inverse">
                        Perfil destacado
                        <span class="badge badge-premium">
                            <x-heroicon-m-star class="size-4 shrink-0" aria-hidden="true" />
                            Próximamente
                        </span>
                    </p>
                    <p class="mt-1 text-sm text-fg-inverse/75">Más visibilidad, fotos de tu consultorio y cuántos pacientes te contactaron.</p>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $joinUrl }}" class="btn btn-inverse">
                    Quiero aparecer en MeeMedico
                    <x-heroicon-m-arrow-right class="size-5" aria-hidden="true" />
                </a>
                <a href="{{ $notifyUrl }}" class="btn btn-outline-inverse">Avísame del perfil destacado</a>
            </div>
        </div>

        <div class="relative min-h-72 lg:min-h-full">
            <img
                src="/images/home/join-640.webp"
                srcset="/images/home/join-640.webp 640w, /images/home/join-1080.webp 1080w"
                sizes="(min-width: 64rem) 32rem, 100vw"
                alt=""
                width="640"
                height="480"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover"
            >
            <div class="float-card bottom-6 left-6 right-6 sm:right-auto" aria-hidden="true">
                <span class="float-card-icon bg-premium text-brand-fg">
                    <x-heroicon-m-chart-bar class="size-5" />
                </span>
                <span>
                    <span class="block text-sm font-semibold text-fg">Tus contactos, medidos</span>
                    <span class="block text-xs text-fg-muted">Cuántos pacientes te llamaron cada mes</span>
                </span>
            </div>
        </div>
    </div>
</section>

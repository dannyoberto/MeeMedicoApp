{{--
    Composición fotográfica del hero: foto principal, formas de marca y dos tarjetas
    flotantes. Solo desde lg; en móvil se usa hero-media-mobile.

    La foto se sirve con <picture> y una fuente solo para lg: en móvil el <img> se queda
    con un GIF de 1 px y no descarga los 70-140 KB de la foto (LCP, design-system §10).

    Fotos de ilustración (Unsplash; créditos en design-system §13.1). No son médicos del
    directorio: las tarjetas nunca dicen nada de la persona de la foto (ni nombre ni
    "verificado"), solo del directorio.
--}}
@props(['total', 'place'])
<div {{ $attributes->class('hero-media') }} aria-hidden="true">
    <span class="hero-shape hero-shape-gold"></span>
    <span class="hero-shape hero-shape-petrol"></span>
    <span class="hero-shape hero-shape-ring"></span>

    <picture>
        <source
            media="(min-width: 64rem)"
            type="image/webp"
            srcset="/images/home/hero-480.webp 480w, /images/home/hero-840.webp 840w"
            sizes="(min-width: 80rem) 30rem, 40vw"
        >
        <img
            src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
            alt=""
            width="480"
            height="600"
            class="hero-photo"
            fetchpriority="high"
            decoding="async"
        >
    </picture>

    <div class="float-card -left-12 top-[14%]">
        <span class="float-card-icon bg-primary text-primary-fg">
            <x-heroicon-m-user-group class="size-5" />
        </span>
        <span>
            <span class="block text-xl font-extrabold leading-tight text-fg">{{ number_format($total, 0, ',', '.') }}</span>
            <span class="block text-xs text-fg-muted">médicos en {{ $place }}</span>
        </span>
    </div>

    <div class="float-card float-card-delay -right-8 bottom-[12%]">
        <span class="float-card-icon bg-premium text-brand-fg">
            <x-heroicon-o-chat-bubble-left-right class="size-5" />
        </span>
        <span>
            <span class="block text-sm font-semibold text-fg">Llama o escribe por WhatsApp</span>
            <span class="block text-xs text-fg-muted">Directo al consultorio, sin intermediarios</span>
        </span>
    </div>
</div>

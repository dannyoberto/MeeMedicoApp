{{--
    Composición fotográfica del hero: foto principal, formas de marca y dos tarjetas
    flotantes. Solo desde lg; en móvil la sustituye la fila de avatares.

    La foto se sirve con <picture> y una fuente solo para lg: en móvil el <img> se queda
    con un GIF de 1 px y no descarga los 70-140 KB de la foto (LCP, design-system §10).

    Fotos de ilustración (Unsplash, licencia libre; créditos en design-system §13.1):
    no son médicos del directorio, por eso no llevan nombre.
--}}
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

    <div class="float-card -left-10 top-[16%]">
        <span class="float-card-icon bg-success text-success-solid">
            <x-heroicon-m-shield-check class="size-6" />
        </span>
        <span>
            <span class="block text-sm font-semibold text-fg">Licencia verificada</span>
            <span class="block text-xs text-fg-muted">Busca el escudo verde</span>
        </span>
    </div>

    <div class="float-card float-card-delay -right-6 bottom-[12%]">
        <img src="/images/home/doctor-coat-160.webp" alt="" width="40" height="40" class="avatar" style="--avatar-size: 2.5rem" loading="lazy">
        <span>
            <span class="block text-sm font-semibold text-fg">Escríbele por WhatsApp</span>
            <span class="block text-xs text-fg-muted">Directo al consultorio</span>
        </span>
        <span class="float-card-icon bg-primary-soft text-primary">
            <x-heroicon-o-chat-bubble-left-right class="size-5" />
        </span>
    </div>
</div>

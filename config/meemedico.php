<?php

return [

    /*
    | Slugs que ninguna entidad puede usar: colisionarían con las rutas públicas
    | /{pais}/medicos/{slug} y /{pais}/{especialidad}/{ciudad} (AGENTS.md).
    | Los slugs de país se añaden en tiempo de ejecución desde la tabla countries.
    */

    'reserved_slugs' => [
        'medicos',
        'especialidades',
        'clinicas',
        'admin',
        'api',
        'assets',
        'buscar',
    ],

    /*
    | Disco de las imágenes del directorio (logos de establecimientos). Hoy, el disco
    | `public` de Laravel (storage/app/public, servido en /storage tras storage:link).
    | Pasar a Cloudflare R2 o S3 es definir ese disco en filesystems.php y cambiar esta
    | variable: lo guardado en la base es la ruta relativa, no la URL.
    */

    'media_disk' => env('MEDIA_DISK', 'public'),

    /*
    | Canal por el que un médico pide, desde su ficha, corregir sus datos o salir del
    | directorio (MODELO-DOMINIO.md §2.10). Son enlaces estáticos: la ficha sigue siendo
    | cacheable. El número de WhatsApp es por país (ISO alfa-2) y en E.164; sin número,
    | la ficha solo ofrece el correo.
    */

    'operations' => [
        'email' => env('OPERATIONS_EMAIL', 'medicos@meemedico.com'),
        'whatsapp' => [
            'CR' => env('OPERATIONS_WHATSAPP_CR'),
            'GT' => env('OPERATIONS_WHATSAPP_GT'),
            'DO' => env('OPERATIONS_WHATSAPP_DO'),
            'VE' => env('OPERATIONS_WHATSAPP_VE'),
        ],
    ],

];

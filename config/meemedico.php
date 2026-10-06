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
    ],

    /*
    | Disco de las imágenes del directorio (logos de establecimientos). Hoy, el disco
    | `public` de Laravel (storage/app/public, servido en /storage tras storage:link).
    | Pasar a Cloudflare R2 o S3 es definir ese disco en filesystems.php y cambiar esta
    | variable: lo guardado en la base es la ruta relativa, no la URL.
    */

    'media_disk' => env('MEDIA_DISK', 'public'),

];

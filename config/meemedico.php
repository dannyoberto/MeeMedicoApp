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

];

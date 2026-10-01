<?php

use App\Domain\Directory\Cdn\LogCdnPurger;

return [

    /*
    | Purga de la caché de página completa (ARQUITECTURA.md §8.1). Al publicar,
    | actualizar o fusionar un médico se purgan su URL y los listados que lo contienen.
    |
    | 'log' solo registra las rutas: sirve mientras no haya Cloudflare delante.
    | Conectar Cloudflare = escribir su driver y cambiar CDN_DRIVER, sin tocar las Actions.
    */

    'driver' => env('CDN_DRIVER', 'log'),

    'drivers' => [
        'log' => LogCdnPurger::class,
    ],

];

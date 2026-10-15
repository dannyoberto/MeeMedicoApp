<?php

// El backoffice tutea (como sus propios textos); la traducción de Filament usa "usted".
// Solo las claves que cambian: Laravel las fusiona con las del paquete.
return [

    'heading' => 'Entra a tu cuenta',

    'actions' => [

        'request_password_reset' => [
            'label' => '¿Olvidaste tu contraseña?',
        ],

    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Demasiados intentos. Prueba de nuevo en :seconds segundos.',
            'body' => 'Prueba de nuevo en :seconds segundos.',
        ],

    ],

];

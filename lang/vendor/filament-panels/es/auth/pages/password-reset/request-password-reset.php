<?php

// Tuteo, como el resto del backoffice (ver login.php en esta carpeta).
return [

    'form' => [

        'actions' => [

            'request' => [
                'label' => 'Enviar enlace',
            ],

        ],

    ],

    'notifications' => [

        'sent' => [
            'body' => 'Si hay una cuenta con ese correo, te llegará un enlace para restablecer la contraseña.',
        ],

        'throttled' => [
            'body' => 'Prueba de nuevo en :seconds segundos.',
        ],

    ],

];

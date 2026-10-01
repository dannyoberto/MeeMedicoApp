<?php

return [

    /*
    | Fuente por defecto de un lote subido con la plantilla de MeeMedico. La fuente
    | forma parte de la referencia externa (source, ID del médico): cambiarla entre
    | cargas del mismo origen impediría reconocer las fichas ya importadas.
    */
    'default_source' => 'plantilla_meemedico',

    /*
    | Nivel 4 de la cascada (§12.3): similitud trigram de nombre, en la misma ciudad,
    | a partir de la cual una fila se manda a revisión como posible duplicado.
    | Nunca produce una fusión: solo una sugerencia para un humano.
    */
    'name_similarity_threshold' => 0.6,

    /* Filas por bloque al leer y procesar. */
    'chunk_size' => 500,

    /* Tamaño máximo del archivo subido desde el backoffice (KB). */
    'max_upload_kb' => 20 * 1024,

];

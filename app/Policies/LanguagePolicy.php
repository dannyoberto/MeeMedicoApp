<?php

namespace App\Policies;

/**
 * Idiomas no tiene permiso propio en §16.2: es un catálogo de referencia y se
 * gestiona con geography.manage.
 */
class LanguagePolicy extends GeographyPolicy {}

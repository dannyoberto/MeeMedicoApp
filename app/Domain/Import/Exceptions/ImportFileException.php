<?php

namespace App\Domain\Import\Exceptions;

use DomainException;

/**
 * El archivo no se puede cargar (otro país, plantilla alterada, ya cargado…).
 * El mensaje está pensado para mostrarse tal cual a quien lo subió.
 */
class ImportFileException extends DomainException {}

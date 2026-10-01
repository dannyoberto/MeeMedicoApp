<?php

namespace App\Domain\Identity\Exceptions;

use DomainException;

/**
 * Una operación de identidad violaría una regla del dominio (por ejemplo, dejar el
 * backoffice sin administradores). El mensaje está pensado para mostrarse al operador.
 */
class IdentityRuleException extends DomainException {}

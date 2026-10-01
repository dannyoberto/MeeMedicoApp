<?php

namespace App\Domain\Directory\Exceptions;

use DomainException;

/**
 * Una operación sobre el directorio violaría una regla del dominio. El mensaje está
 * pensado para mostrarse tal cual al operador del backoffice.
 */
class DirectoryRuleException extends DomainException
{
    /**
     * @param  array<int|string, string>  $details  por ejemplo, los requisitos que faltan
     */
    public function __construct(string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}

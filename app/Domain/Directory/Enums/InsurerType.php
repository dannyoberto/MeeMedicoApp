<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK insurers_type_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum InsurerType: string implements HasLabel
{
    case Private = 'private';
    case Public = 'public';

    public function getLabel(): string
    {
        return match ($this) {
            self::Private => 'Privada',
            self::Public => 'Pública',
        };
    }
}

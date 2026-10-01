<?php

namespace App\Domain\Import\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK import_rows_resolution_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ImportResolution: string implements HasLabel
{
    case CreateNew = 'create_new';
    case LinkExisting = 'link_existing';
    case Discard = 'discard';

    public function getLabel(): string
    {
        return match ($this) {
            self::CreateNew => 'Crear nueva',
            self::LinkExisting => 'Vincular a existente',
            self::Discard => 'Descartar',
        };
    }
}

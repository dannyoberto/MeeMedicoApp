<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK doctors_source_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum DoctorSource: string implements HasLabel
{
    case Import = 'import';
    case Admin = 'admin';
    case Claim = 'claim';

    public function getLabel(): string
    {
        return match ($this) {
            self::Import => 'Importación',
            self::Admin => 'Administración',
            self::Claim => 'Reclamación',
        };
    }
}

<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK doctor_contacts_source_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ContactSource: string implements HasLabel
{
    case Import = 'import';
    case Admin = 'admin';
    case Doctor = 'doctor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Import => 'Importación',
            self::Admin => 'Administración',
            self::Doctor => 'Médico',
        };
    }
}

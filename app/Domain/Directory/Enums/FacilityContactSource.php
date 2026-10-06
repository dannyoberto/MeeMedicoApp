<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK facility_contacts_source_chk (DATABASE.md).
 * Sin 'doctor' ni 'facility': no hay cuentas de establecimiento en Fase 1 (§9.12).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum FacilityContactSource: string implements HasLabel
{
    case Import = 'import';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Import => 'Importación',
            self::Admin => 'Administración',
        };
    }
}

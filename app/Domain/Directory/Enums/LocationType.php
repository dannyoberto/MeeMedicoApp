<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK doctor_locations_type_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum LocationType: string implements HasLabel
{
    case Office = 'office';
    case Clinic = 'clinic';
    case Hospital = 'hospital';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Office => 'Consultorio',
            self::Clinic => 'Clínica',
            self::Hospital => 'Hospital',
            self::Other => 'Otro',
        };
    }
}

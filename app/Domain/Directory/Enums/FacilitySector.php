<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK facilities_sector_chk (DATABASE.md).
 * También lo usa facility_networks.sector, cuyo CHECK se prueba aparte en SchemaConstraintsTest.
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum FacilitySector: string implements HasLabel
{
    case Public = 'public';
    case Private = 'private';
    case Mixed = 'mixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Público',
            self::Private => 'Privado',
            self::Mixed => 'Mixto',
        };
    }
}

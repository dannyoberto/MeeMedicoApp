<?php

namespace App\Domain\Geo\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK city_aliases_source_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum CityAliasSource: string implements HasLabel
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

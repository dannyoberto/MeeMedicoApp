<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK contact_events_source_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ContactEventSource: string implements HasLabel
{
    case Profile = 'profile';
    case Listing = 'listing';
    case Map = 'map';

    public function getLabel(): string
    {
        return match ($this) {
            self::Profile => 'Ficha',
            self::Listing => 'Listado',
            self::Map => 'Mapa',
        };
    }
}

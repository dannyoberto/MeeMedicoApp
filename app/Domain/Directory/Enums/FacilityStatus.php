<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK facilities_status_chk (DATABASE.md).
 * Mientras no exista la landing pública, Active significa "listo para publicar" (§9.10).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum FacilityStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Active => Heroicon::OutlinedCheckCircle,
            self::Inactive => Heroicon::OutlinedMinusCircle,
        };
    }
}

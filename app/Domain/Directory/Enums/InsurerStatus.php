<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK insurers_status_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum InsurerStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Inactive => 'Inactiva',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Active => Heroicon::OutlinedCheckCircle,
            self::Inactive => Heroicon::OutlinedMinusCircle,
        };
    }
}

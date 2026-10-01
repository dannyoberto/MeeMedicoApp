<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK doctors_status_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum DoctorStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Merged = 'merged';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Publicado',
            self::Inactive => 'Despublicado',
            self::Suspended => 'Suspendido',
            self::Merged => 'Fusionado',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Suspended => 'danger',
            self::Merged => 'info',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Active => Heroicon::OutlinedGlobeAlt,
            self::Inactive => Heroicon::OutlinedEyeSlash,
            self::Suspended => Heroicon::OutlinedNoSymbol,
            self::Merged => Heroicon::OutlinedArrowsPointingIn,
        };
    }
}

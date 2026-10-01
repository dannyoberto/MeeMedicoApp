<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK doctors_claim_status_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ClaimStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unclaimed = 'unclaimed';
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unclaimed => 'No reclamado',
            self::Pending => 'Reclamación pendiente',
            self::Claimed => 'Reclamado',
            self::Rejected => 'Reclamación rechazada',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Unclaimed => 'gray',
            self::Pending => 'warning',
            self::Claimed => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Unclaimed => Heroicon::OutlinedUser,
            self::Pending => Heroicon::OutlinedClock,
            self::Claimed => Heroicon::OutlinedCheckBadge,
            self::Rejected => Heroicon::OutlinedXCircle,
        };
    }
}

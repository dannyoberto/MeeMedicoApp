<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK doctors_verification_status_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum VerificationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unverified => 'Sin verificar',
            self::Pending => 'Verificación pendiente',
            self::Verified => 'Verificado',
            self::Rejected => 'Verificación rechazada',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Unverified => 'gray',
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Unverified => Heroicon::OutlinedQuestionMarkCircle,
            self::Pending => Heroicon::OutlinedClock,
            self::Verified => Heroicon::OutlinedShieldCheck,
            self::Rejected => Heroicon::OutlinedXCircle,
        };
    }
}

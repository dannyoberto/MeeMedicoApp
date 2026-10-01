<?php

namespace App\Domain\Import\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK import_rows_status_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ImportRowStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Normalized = 'normalized';
    case Matched = 'matched';
    case New = 'new';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Applied = 'applied';
    case Skipped = 'skipped';
    case Failed = 'failed';
    case Suppressed = 'suppressed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Normalized => 'Normalizada',
            self::Matched => 'Coincidencia fuerte',
            self::New => 'Nueva',
            self::NeedsReview => 'Requiere revisión',
            self::Approved => 'Aprobada',
            self::Applied => 'Aplicada',
            self::Skipped => 'Omitida',
            self::Failed => 'Fallida',
            self::Suppressed => 'Suprimida',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Normalized => 'info',
            self::Matched => 'info',
            self::New => 'primary',
            self::NeedsReview => 'warning',
            self::Approved => 'primary',
            self::Applied => 'success',
            self::Skipped => 'gray',
            self::Failed => 'danger',
            self::Suppressed => 'danger',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Normalized => Heroicon::OutlinedAdjustmentsHorizontal,
            self::Matched => Heroicon::OutlinedLink,
            self::New => Heroicon::OutlinedPlusCircle,
            self::NeedsReview => Heroicon::OutlinedExclamationTriangle,
            self::Approved => Heroicon::OutlinedHandThumbUp,
            self::Applied => Heroicon::OutlinedCheckCircle,
            self::Skipped => Heroicon::OutlinedMinusCircle,
            self::Failed => Heroicon::OutlinedXCircle,
            self::Suppressed => Heroicon::OutlinedNoSymbol,
        };
    }
}

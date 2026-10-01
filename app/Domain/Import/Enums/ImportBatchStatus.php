<?php

namespace App\Domain\Import\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK import_batches_status_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ImportBatchStatus: string implements HasColor, HasIcon, HasLabel
{
    case Ingesting = 'ingesting';
    case Normalizing = 'normalizing';
    case Matching = 'matching';
    case Review = 'review';
    case Applying = 'applying';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ingesting => 'Ingestando',
            self::Normalizing => 'Normalizando',
            self::Matching => 'Buscando coincidencias',
            self::Review => 'En revisión',
            self::Applying => 'Aplicando',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Ingesting => 'info',
            self::Normalizing => 'info',
            self::Matching => 'info',
            self::Review => 'warning',
            self::Applying => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Ingesting => Heroicon::OutlinedArrowPath,
            self::Normalizing => Heroicon::OutlinedArrowPath,
            self::Matching => Heroicon::OutlinedArrowPath,
            self::Review => Heroicon::OutlinedEye,
            self::Applying => Heroicon::OutlinedArrowPath,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedXCircle,
        };
    }
}

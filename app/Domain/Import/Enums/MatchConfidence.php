<?php

namespace App\Domain\Import\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK import_rows_confidence_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum MatchConfidence: string implements HasColor, HasLabel
{
    case Strong = 'strong';
    case Weak = 'weak';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Strong => 'Fuerte',
            self::Weak => 'Débil',
            self::None => 'Ninguna',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Strong => 'success',
            self::Weak => 'warning',
            self::None => 'gray',
        };
    }
}

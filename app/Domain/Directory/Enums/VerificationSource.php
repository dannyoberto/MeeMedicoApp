<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK doctors_verification_source_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum VerificationSource: string implements HasLabel
{
    case OfficialRegistry = 'official_registry';
    case Document = 'document';
    case Manual = 'manual';
    case Claim = 'claim';

    public function getLabel(): string
    {
        return match ($this) {
            self::OfficialRegistry => 'Registro oficial',
            self::Document => 'Documento',
            self::Manual => 'Manual',
            self::Claim => 'Reclamación',
        };
    }
}

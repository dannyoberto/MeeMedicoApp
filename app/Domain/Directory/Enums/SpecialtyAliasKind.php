<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK specialty_aliases_kind_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum SpecialtyAliasKind: string implements HasLabel
{
    case Import = 'import';
    case Seo = 'seo';
    case Local = 'local';

    public function getLabel(): string
    {
        return match ($this) {
            self::Import => 'Importación',
            self::Seo => 'Sinónimo de búsqueda',
            self::Local => 'Variante local',
        };
    }
}

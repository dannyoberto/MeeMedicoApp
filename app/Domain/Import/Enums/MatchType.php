<?php

namespace App\Domain\Import\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK import_rows_match_type_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum MatchType: string implements HasLabel
{
    case ExternalRef = 'external_ref';
    case License = 'license';
    case Phone = 'phone';
    case Email = 'email';
    case NameCity = 'name_city';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::ExternalRef => 'Referencia externa',
            self::License => 'Licencia',
            self::Phone => 'Teléfono',
            self::Email => 'Correo',
            self::NameCity => 'Nombre y ciudad',
            self::None => 'Ninguna',
        };
    }
}

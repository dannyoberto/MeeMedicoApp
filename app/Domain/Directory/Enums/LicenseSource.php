<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK doctors_license_source_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum LicenseSource: string implements HasLabel
{
    case OfficialRegistry = 'official_registry';
    case ImportThirdParty = 'import_third_party';
    case SelfDeclared = 'self_declared';
    case Admin = 'admin';
    case Claim = 'claim';

    public function getLabel(): string
    {
        return match ($this) {
            self::OfficialRegistry => 'Registro oficial',
            self::ImportThirdParty => 'Importación de terceros',
            self::SelfDeclared => 'Autodeclarada',
            self::Admin => 'Administración',
            self::Claim => 'Reclamación',
        };
    }
}

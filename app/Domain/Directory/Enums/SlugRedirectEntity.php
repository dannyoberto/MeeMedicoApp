<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK slug_redirects_entity_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum SlugRedirectEntity: string implements HasLabel
{
    case Doctor = 'doctor';
    case Specialty = 'specialty';
    case City = 'city';
    case Region = 'region';
    case Facility = 'facility';
    case Insurer = 'insurer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Doctor => 'Médico',
            self::Specialty => 'Especialidad',
            self::City => 'Ciudad',
            self::Region => 'Región',
            self::Facility => 'Establecimiento',
            self::Insurer => 'Aseguradora',
        };
    }
}

<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK doctors_gender_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum DoctorGender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case Undisclosed = 'undisclosed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Femenino',
            self::Other => 'Otro',
            self::Undisclosed => 'No indicado',
        };
    }
}

<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Valores: deben coincidir con el CHECK facilities_type_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum FacilityType: string implements HasLabel
{
    case Hospital = 'hospital';
    case Clinic = 'clinic';
    case MedicalCenter = 'medical_center';
    case HealthCenter = 'health_center';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hospital => 'Hospital',
            self::Clinic => 'Clínica',
            self::MedicalCenter => 'Centro médico',
            self::HealthCenter => 'Centro de salud',
        };
    }

    /**
     * Modalidad que se propone al asociar un médico a una sede (doctor_locations.location_type).
     * Un centro médico es una torre de consultorios: lo habitual es consultorio propio.
     */
    public function defaultLocationType(): LocationType
    {
        return match ($this) {
            self::Hospital => LocationType::Hospital,
            self::Clinic, self::HealthCenter => LocationType::Clinic,
            self::MedicalCenter => LocationType::Office,
        };
    }
}

<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK doctor_contacts_type_chk (DATABASE.md).
 * También lo usa facility_contacts.type, cuyo CHECK se prueba aparte en SchemaConstraintsTest.
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ContactType: string implements HasIcon, HasLabel
{
    case Phone = 'phone';
    case Mobile = 'mobile';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Website = 'website';

    public function getLabel(): string
    {
        return match ($this) {
            self::Phone => 'Teléfono',
            self::Mobile => 'Móvil',
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Correo',
            self::Website => 'Sitio web',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Phone => Heroicon::OutlinedPhone,
            self::Mobile => Heroicon::OutlinedDevicePhoneMobile,
            self::Whatsapp => Heroicon::OutlinedChatBubbleLeftRight,
            self::Email => Heroicon::OutlinedEnvelope,
            self::Website => Heroicon::OutlinedGlobeAlt,
        };
    }
}

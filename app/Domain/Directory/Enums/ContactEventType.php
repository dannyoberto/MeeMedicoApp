<?php

namespace App\Domain\Directory\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Valores: deben coincidir con el CHECK contact_events_type_chk (DATABASE.md).
 * Etiqueta, tono e icono: design-system.md §8 (el tono usa el vocabulario de Filament).
 */
enum ContactEventType: string implements HasIcon, HasLabel
{
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Website = 'website';
    case Directions = 'directions';

    public function getLabel(): string
    {
        return match ($this) {
            self::Phone => 'Llamada',
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Correo',
            self::Website => 'Sitio web',
            self::Directions => 'Cómo llegar',
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::Phone => Heroicon::OutlinedPhone,
            self::Whatsapp => Heroicon::OutlinedChatBubbleLeftRight,
            self::Email => Heroicon::OutlinedEnvelope,
            self::Website => Heroicon::OutlinedGlobeAlt,
            self::Directions => Heroicon::OutlinedMapPin,
        };
    }
}

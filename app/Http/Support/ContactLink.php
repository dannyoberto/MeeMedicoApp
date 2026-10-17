<?php

namespace App\Http\Support;

use App\Domain\Directory\Enums\ContactEventType;
use App\Domain\Directory\Enums\ContactType;
use App\Models\Doctor;
use App\Models\DoctorContact;
use Illuminate\Support\Collection;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Un contacto público convertido en enlace: tel:, wa.me, mailto: o web. Lleva el tipo de
 * evento que registra el beacon de contacto (DATABASE.md §14.3).
 *
 * El teléfono sale de value_normalized (E.164, PhoneNormalizer); si el import no pudo
 * normalizarlo, de los dígitos del valor original.
 */
final readonly class ContactLink
{
    public const WHATSAPP_GREETING = 'Hola, le escribo desde MeeMedico.';

    public function __construct(
        public ContactType $type,
        public string $href,
        public string $display,
        public ContactEventType $event,
        public bool $external,
        /** Qué teléfono es ("Móvil", "Fijo" o la etiqueta del contacto): dos "Llamar" iguales no se distinguen. */
        public ?string $kind = null,
    ) {}

    /**
     * Orden en la ficha: lo que el paciente usa para pedir cita va primero.
     */
    private const PRIORITY = [
        'whatsapp' => 0,
        'phone' => 1,
        'email' => 2,
        'website' => 3,
    ];

    public static function from(DoctorContact $contact): ?self
    {
        $value = trim($contact->value);
        $phone = $contact->value_normalized ?: preg_replace('/[^\d+]/', '', $value);
        $phoneDisplay = self::formatPhone($contact->value_normalized) ?? $value;
        $kind = filled($contact->label) ? trim($contact->label) : match ($contact->type) {
            // Muchos imports marcan todo como "phone": el número dice si es móvil o fijo.
            ContactType::Phone, ContactType::Mobile => self::phoneKind($contact->value_normalized)
                ?? ($contact->type === ContactType::Mobile ? 'Móvil' : 'Fijo'),
            default => null,
        };

        return match ($contact->type) {
            ContactType::Phone, ContactType::Mobile => $phone === ''
                ? null
                : new self($contact->type, "tel:{$phone}", $phoneDisplay, ContactEventType::Phone, false, $kind),
            ContactType::Whatsapp => ($digits = preg_replace('/\D/', '', $phone)) === ''
                ? null
                : new self($contact->type, "https://wa.me/{$digits}?text=".rawurlencode(self::WHATSAPP_GREETING), $phoneDisplay, ContactEventType::Whatsapp, true),
            ContactType::Email => new self($contact->type, "mailto:{$value}", $value, ContactEventType::Email, false),
            ContactType::Website => new self(
                $contact->type,
                preg_match('#^https?://#i', $value) ? $value : "https://{$value}",
                preg_replace('#^https?://(www\.)?#i', '', rtrim($value, '/')),
                ContactEventType::Website,
                true,
            ),
        };
    }

    /**
     * Los contactos públicos de la ficha: WhatsApp y teléfono antes que correo y web, y
     * dentro de cada tipo el marcado como principal. Requiere la relación contacts cargada.
     *
     * @return Collection<int, self>
     */
    public static function forDoctor(Doctor $doctor): Collection
    {
        return $doctor->contacts
            ->filter(fn (DoctorContact $contact) => $contact->is_public)
            ->map(fn (DoctorContact $contact) => [self::from($contact), $contact->is_primary])
            ->filter(fn (array $pair) => $pair[0] !== null)
            ->sortBy([
                fn (array $a, array $b) => self::PRIORITY[$a[0]->event->value] <=> self::PRIORITY[$b[0]->event->value],
                fn (array $a, array $b) => $b[1] <=> $a[1],
            ])
            ->map(fn (array $pair) => $pair[0])
            ->values();
    }

    /**
     * "Móvil" o "Fijo" según el plan de numeración del país, o null si no se sabe
     * (números que pueden ser ambas cosas, o sin normalizar).
     */
    private static function phoneKind(?string $e164): ?string
    {
        if ($e164 === null || $e164 === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            return match ($util->getNumberType($util->parse($e164))) {
                PhoneNumberType::MOBILE => 'Móvil',
                PhoneNumberType::FIXED_LINE => 'Fijo',
                default => null,
            };
        } catch (NumberParseException) {
            return null;
        }
    }

    /**
     * "+58 416-6516822" a partir del E.164 que guarda PhoneNormalizer.
     */
    private static function formatPhone(?string $e164): ?string
    {
        if ($e164 === null || $e164 === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            return $util->format($util->parse($e164), PhoneNumberFormat::INTERNATIONAL);
        } catch (NumberParseException) {
            return null;
        }
    }

    /**
     * La acción principal de la tarjeta y de la barra móvil: WhatsApp si lo tiene (en
     * LATAM es el canal que más se usa), si no, el teléfono.
     */
    public static function primary(Doctor $doctor): ?self
    {
        $links = self::forDoctor($doctor);

        return $links->firstWhere('event', ContactEventType::Whatsapp)
            ?? $links->firstWhere('event', ContactEventType::Phone);
    }

    /**
     * La acción secundaria de la barra móvil: el teléfono cuando la principal es WhatsApp.
     */
    public static function secondary(Doctor $doctor): ?self
    {
        $primary = self::primary($doctor);

        return $primary?->event === ContactEventType::Whatsapp
            ? self::forDoctor($doctor)->firstWhere('event', ContactEventType::Phone)
            : null;
    }

    public function label(): string
    {
        return match ($this->event) {
            ContactEventType::Phone => 'Llamar',
            ContactEventType::Whatsapp => 'WhatsApp',
            ContactEventType::Email => 'Escribir',
            ContactEventType::Website => 'Sitio web',
            ContactEventType::Directions => 'Cómo llegar',
        };
    }

    /**
     * Heroicon para <x-dynamic-component> (blade-heroicons, design-system §13).
     */
    public function icon(): string
    {
        return match ($this->event) {
            ContactEventType::Phone => 'heroicon-o-phone',
            ContactEventType::Whatsapp => 'heroicon-o-chat-bubble-left-right',
            ContactEventType::Email => 'heroicon-o-envelope',
            ContactEventType::Website => 'heroicon-o-globe-alt',
            ContactEventType::Directions => 'heroicon-o-map-pin',
        };
    }
}

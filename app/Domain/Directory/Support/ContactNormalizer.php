<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\ContactType;
use Illuminate\Validation\ValidationException;

/**
 * value_normalized de un contacto (DATABASE.md §9.7, §9.12): E.164 para teléfonos,
 * minúsculas para correo, esquema y host en minúsculas para la web. La comparten los
 * contactos de médicos y de establecimientos, para que el mismo número dé la misma clave.
 */
final class ContactNormalizer
{
    public const PHONE_TYPES = [ContactType::Phone, ContactType::Mobile, ContactType::Whatsapp];

    /**
     * @param  string  $countryCode  ISO 3166-1 alfa-2 con el que se interpreta un teléfono
     *
     * @throws ValidationException (clave: value)
     */
    public static function normalize(ContactType $type, string $value, string $countryCode): string
    {
        $value = trim($value);

        if (in_array($type, self::PHONE_TYPES, true)) {
            return PhoneNormalizer::toE164($value, $countryCode)
                ?? throw ValidationException::withMessages(['value' => "No es un teléfono válido de {$countryCode}."]);
        }

        if ($type === ContactType::Email) {
            $email = mb_strtolower($value);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['value' => 'No es un correo válido.']);
            }

            return $email;
        }

        // Sitio web: esquema obligatorio y host en minúsculas.
        $url = preg_match('#^https?://#i', $value) ? $value : "https://{$value}";
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || empty($parts['host']) || ! str_contains($parts['host'], '.')) {
            throw ValidationException::withMessages(['value' => 'No es una dirección web válida.']);
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}

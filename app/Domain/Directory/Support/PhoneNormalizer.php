<?php

namespace App\Domain\Directory\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Teléfono a E.164 (+50622223333). Es la forma de doctor_contacts.value_normalized,
 * de las supresiones y de import_rows.n_phone_e164, y una señal de deduplicación.
 *
 * Usa libphonenumber con la región ISO del país y no countries.dial_code: República
 * Dominicana comparte +1 con otros países y usa tres códigos de área (809/829/849).
 */
final class PhoneNormalizer
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alfa-2 del país del médico (CR, GT, DO, VE)
     * @return string|null E.164, o null si el número no es válido. Nunca se inventa un valor.
     */
    public static function toE164(string $raw, string $countryCode): ?string
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($raw, strtoupper($countryCode));
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($number)
            ? $util->format($number, PhoneNumberFormat::E164)
            : null;
    }
}

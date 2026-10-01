<?php

namespace App\Domain\Import\Actions;

use App\Domain\Directory\Support\NameNormalizer;
use App\Domain\Directory\Support\PhoneNormalizer;
use App\Domain\Geo\Support\Normalize;
use App\Domain\Import\Enums\ImportBatchStatus;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\Support\CatalogResolver;
use App\Domain\Import\Support\ImportBatchCounters;
use App\Domain\Import\Support\RowGroup;
use App\Domain\Import\Support\RowIssue;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Etapa 2 (§12.3): rellena los campos n_* y traduce texto libre al catálogo.
 * Lo que no se resuelve queda en needs_review con su incidencia: NUNCA se inventa
 * un valor. Reejecutable (p. ej., tras asignar una ciudad desconocida).
 */
class NormalizeImportBatchAction
{
    /** Filas que se pueden (re)normalizar: nunca las ya decididas por un humano o aplicadas. */
    public const ELIGIBLE = [
        ImportRowStatus::Pending, ImportRowStatus::Normalized, ImportRowStatus::NeedsReview,
        ImportRowStatus::New, ImportRowStatus::Matched, ImportRowStatus::Failed,
    ];

    private const DOCTOR_REQUIRED = ['first_name' => 'Nombres', 'last_name' => 'Apellidos', 'specialty_1' => 'Especialidad principal'];

    public function execute(ImportBatch $batch): ImportBatch
    {
        ImportBatchCounters::refresh($batch, ImportBatchStatus::Normalizing);
        $resolver = new CatalogResolver($batch->country);

        // Se cargan TODAS las filas para ver cada grupo completo (los conflictos entre
        // filas del mismo médico lo exigen), pero solo se reescriben las elegibles.
        foreach (RowGroup::from($batch->rows()->get()) as $group) {
            DB::transaction(fn () => $this->normalizeGroup($group, $resolver, $batch));
        }

        return ImportBatchCounters::refresh($batch);
    }

    private function normalizeGroup(RowGroup $group, CatalogResolver $resolver, ImportBatch $batch): void
    {
        $groupIssues = [];

        // ---------- Datos del médico (comunes al grupo; sus incidencias van a la primera fila) ----------
        if (str_starts_with($group->ref, 'fila-')) {
            $groupIssues[] = RowIssue::make(RowIssue::MISSING, 'Falta el "ID del médico".', 'doctor_ref');
        }

        foreach (self::DOCTOR_REQUIRED as $field => $label) {
            if ($group->value($field) === null) {
                $groupIssues[] = RowIssue::make(RowIssue::MISSING, "Falta \"{$label}\".", $field);
            }
        }

        foreach ($group->conflicts() as $field => $values) {
            $groupIssues[] = RowIssue::make(RowIssue::GROUP_CONFLICT, "Las filas de este médico no coinciden en {$field}: {$values}. Se usará el de la primera fila si apruebas.", $field);
        }

        $specialtyIds = [];
        foreach (['specialty_1', 'specialty_2', 'specialty_3'] as $field) {
            if (($text = $group->value($field)) === null) {
                continue;
            }
            if ($id = $resolver->specialty($text)) {
                $specialtyIds[] = $id;
            } else {
                $groupIssues[] = RowIssue::make(RowIssue::UNKNOWN_SPECIALTY, "Especialidad no reconocida: \"{$text}\".", $field, $text);
            }
        }

        if (($gender = $group->value('gender')) !== null && CatalogResolver::gender($gender) === null) {
            $groupIssues[] = RowIssue::make(RowIssue::UNKNOWN_VALUE, "Género no reconocido: \"{$gender}\". Se ignora.", 'gender', $gender, RowIssue::WARNING);
        }

        foreach (CatalogResolver::splitList($group->value('languages')) as $language) {
            if ($resolver->language($language) === null) {
                $groupIssues[] = RowIssue::make(RowIssue::UNKNOWN_VALUE, "Idioma no reconocido: \"{$language}\". Se ignora.", 'languages', $language, RowIssue::WARNING);
            }
        }

        $email = $group->value('email');
        if ($email !== null && ! filter_var(mb_strtolower($email), FILTER_VALIDATE_EMAIL)) {
            $groupIssues[] = RowIssue::make(RowIssue::INVALID_EMAIL, "Correo no válido: \"{$email}\". Se ignora.", 'email', $email, RowIssue::WARNING);
            $email = null;
        }

        $firstName = $group->value('first_name');
        $lastName = $group->value('last_name');
        $doctor = [
            'n_first_name' => $firstName,
            'n_last_name' => $lastName,
            'n_name_key' => ($firstName || $lastName) ? NameNormalizer::normalize("{$firstName} {$lastName}") : null,
            'n_license' => $group->value('license_number'),
            'n_email' => $email ? mb_strtolower($email) : null,
            'n_country_id' => $batch->country_id,
            'n_specialty_ids' => array_values(array_unique($specialtyIds)),
        ];

        // ---------- Consultorio (una fila cada uno) ----------
        foreach ($group->rows as $index => $row) {
            if (! in_array($row->status, self::ELIGIBLE, true)) {
                continue;
            }

            $issues = $index === 0 ? $groupIssues : [];
            $raw = $row->raw_payload;

            $address = RowGroup::clean($raw['address'] ?? null);
            if ($address === null) {
                $issues[] = RowIssue::make(RowIssue::MISSING, 'Falta la "Dirección".', 'address');
            }

            $cityText = RowGroup::clean($raw['city'] ?? null);
            [$cityId, $cityIssue] = $resolver->city($cityText, RowGroup::clean($raw['region'] ?? null));
            if ($cityIssue !== null) {
                $issues[] = RowIssue::make($cityIssue, match ($cityIssue) {
                    RowIssue::MISSING => 'Falta la "Ciudad".',
                    RowIssue::AMBIGUOUS_CITY => "Hay varias ciudades \"{$cityText}\": indica la provincia o asígnala.",
                    default => "Ciudad no reconocida: \"{$cityText}\".",
                }, 'city', $cityText);
            }

            $phone = null;
            foreach (['phone', 'mobile', 'whatsapp'] as $field) {
                if (($value = RowGroup::clean($raw[$field] ?? null)) === null) {
                    continue;
                }
                $e164 = PhoneNormalizer::toE164($value, $batch->country->code);
                if ($e164 === null) {
                    $issues[] = RowIssue::make(RowIssue::INVALID_PHONE, "Número no válido en {$field}: \"{$value}\". Se ignora.", $field, $value, RowIssue::WARNING);
                }
                $phone ??= $e164;
            }

            if (($type = RowGroup::clean($raw['location_type'] ?? null)) !== null && CatalogResolver::locationType($type) === null) {
                $issues[] = RowIssue::make(RowIssue::UNKNOWN_VALUE, "Tipo de consultorio no reconocido: \"{$type}\". Se usará Consultorio.", 'location_type', $type, RowIssue::WARNING);
            }

            $hasError = collect($issues)->contains(fn ($i) => $i['level'] === RowIssue::ERROR);

            $row->forceFill([
                ...$doctor,
                'n_city_id' => $cityId,
                'n_phone_e164' => $phone,
                'n_address' => $address ? mb_substr(Normalize::text(trim($address.' '.($raw['address_2'] ?? ''))), 0, 300) : null,
                'validation_errors' => $issues ?: null,
                'status' => $hasError ? ImportRowStatus::NeedsReview : ImportRowStatus::Normalized,
                // Se recalculan en la etapa 3.
                'match_type' => null,
                'match_confidence' => 'none',
                'matched_doctor_id' => null,
                'candidate_doctor_ids' => null,
            ])->save();
        }
    }
}

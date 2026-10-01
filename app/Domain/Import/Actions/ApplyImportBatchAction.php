<?php

namespace App\Domain\Import\Actions;

use App\Domain\Directory\Actions\CreateDoctorAction;
use App\Domain\Directory\Actions\DoctorContactsAction;
use App\Domain\Directory\Actions\DoctorLanguagesAction;
use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Actions\UpdateDoctorAction;
use App\Domain\Directory\Enums\ContactSource;
use App\Domain\Directory\Enums\ContactType;
use App\Domain\Directory\Enums\DoctorSource;
use App\Domain\Directory\Enums\LicenseSource;
use App\Domain\Directory\Enums\LocationStatus;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Support\PhoneNormalizer;
use App\Domain\Geo\Support\Normalize;
use App\Domain\Import\Enums\ImportBatchStatus;
use App\Domain\Import\Enums\ImportResolution;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\Support\CatalogResolver;
use App\Domain\Import\Support\ImportBatchCounters;
use App\Domain\Import\Support\RowGroup;
use App\Domain\Import\Support\RowIssue;
use App\Models\Doctor;
use App\Models\DoctorExternalReference;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Language;
use App\Models\Location;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Etapa 4 (§12.3): crea o completa fichas a partir de las filas listas.
 *
 * - Solo filas en matched, new o approved. Lo que espera revisión NUNCA se aplica.
 * - Nunca escribe en doctors por su cuenta: usa las mismas Actions que el backoffice,
 *   así las supresiones, los slugs y las normalizaciones son idénticos.
 * - Ficha existente: solo AÑADE lo que falta; un valor distinto es un conflicto que se
 *   anota, nunca se sobrescribe (decisión de la Etapa 6).
 * - Nunca publica: eso es PublishImportBatchAction, un paso aparte.
 * - Cada médico en su propia transacción: uno que falla no tumba el lote.
 */
class ApplyImportBatchAction
{
    private const APPLICABLE = [ImportRowStatus::Matched, ImportRowStatus::New, ImportRowStatus::Approved];

    /** Campos que se completan si la ficha existente los tiene vacíos. */
    private const FILLABLE = ['professional_name', 'gender', 'license_number', 'headline', 'bio', 'education', 'experience'];

    public function __construct(
        private readonly CreateDoctorAction $createDoctor,
        private readonly UpdateDoctorAction $updateDoctor,
        private readonly DoctorSpecialtiesAction $specialties,
        private readonly DoctorLocationsAction $locations,
        private readonly DoctorContactsAction $contacts,
        private readonly DoctorLanguagesAction $languages,
    ) {}

    /**
     * @return array{created: int, updated: int, failed: int}
     */
    public function execute(ImportBatch $batch, ?User $actor): array
    {
        ImportBatchCounters::refresh($batch, ImportBatchStatus::Applying);
        $resolver = new CatalogResolver($batch->country);
        $report = ['created' => 0, 'updated' => 0, 'failed' => 0];

        foreach (RowGroup::from($batch->rows()->get()) as $group) {
            if (! in_array($group->first()->status, self::APPLICABLE, true)) {
                continue;
            }

            try {
                $created = DB::transaction(fn () => $this->applyGroup($group, $batch, $resolver, $actor));
                $report[$created ? 'created' : 'updated']++;
            } catch (Throwable $e) {
                report($e);
                $this->markFailed($group, $e);
                $report['failed']++;
            }
        }

        activity()->performedOn($batch)->causedBy($actor)->event('import.applied')->withProperties($report)->log('import.applied');

        $pending = $batch->rows()->whereIn('status', [
            ImportRowStatus::Pending, ImportRowStatus::Normalized, ImportRowStatus::NeedsReview,
            ImportRowStatus::New, ImportRowStatus::Matched, ImportRowStatus::Approved,
        ])->exists();

        $batch->forceFill(['finished_at' => $pending ? null : now()]);
        ImportBatchCounters::refresh($batch, $pending ? ImportBatchStatus::Review : ImportBatchStatus::Completed);

        return $report;
    }

    /**
     * @return bool true si creó una ficha nueva
     */
    private function applyGroup(RowGroup $group, ImportBatch $batch, CatalogResolver $resolver, ?User $actor): bool
    {
        $first = $group->first();
        $issues = $first->validation_errors ?? [];
        $creating = $first->status === ImportRowStatus::New
            || ($first->status === ImportRowStatus::Approved && $first->resolution === ImportResolution::CreateNew);

        $doctor = $creating
            ? $this->create($group, $batch, $actor)
            : Doctor::findOrFail($first->matched_doctor_id);

        if (! $creating) {
            $issues = [...$issues, ...$this->fillMissing($doctor, $group, $actor)];
        }

        foreach ($first->n_specialty_ids ?? [] as $specialtyId) {
            if (! $doctor->specialties()->whereKey($specialtyId)->exists()) {
                $this->specialties->attach($doctor, Specialty::findOrFail($specialtyId), $actor);
            }
        }

        foreach (CatalogResolver::splitList($group->value('languages')) as $text) {
            if ($languageId = $resolver->language($text)) {
                $this->languages->attach($doctor, Language::findOrFail($languageId), $actor);
            }
        }

        foreach (['email' => ContactType::Email, 'website' => ContactType::Website] as $field => $type) {
            if (($value = $group->value($field)) !== null) {
                $issues = [...$issues, ...$this->addContact($doctor, $type, $value, null, $actor)];
            }
        }

        foreach ($group->rows as $index => $row) {
            if (! in_array($row->status, self::APPLICABLE, true)) {
                continue;
            }

            $rowIssues = $index === 0 ? $issues : ($row->validation_errors ?? []);
            $location = $this->location($doctor, $row, $actor);

            foreach (['phone' => ContactType::Phone, 'mobile' => ContactType::Mobile, 'whatsapp' => ContactType::Whatsapp] as $field => $type) {
                if (($value = RowGroup::clean($row->raw_payload[$field] ?? null)) !== null) {
                    $rowIssues = [...$rowIssues, ...$this->addContact($doctor, $type, $value, $location, $actor)];
                }
            }

            $row->forceFill([
                'status' => ImportRowStatus::Applied,
                'applied_doctor_id' => $doctor->getKey(),
                'applied_at' => now(),
                'validation_errors' => $rowIssues ?: null,
            ])->save();
        }

        $this->rememberReference($group, $batch, $doctor);

        return $creating;
    }

    private function create(RowGroup $group, ImportBatch $batch, ?User $actor): Doctor
    {
        $first = $group->first();
        $specialtyIds = $first->n_specialty_ids ?? [];

        $doctor = $this->createDoctor->execute(
            [
                'country_id' => $batch->country_id,
                'first_name' => $first->n_first_name,
                'last_name' => $first->n_last_name,
                'professional_name' => $group->value('professional_name'),
                'gender' => CatalogResolver::gender($group->value('gender'))?->value,
                'license_number' => $first->n_license,
                'license_source' => LicenseSource::ImportThirdParty->value,
            ],
            $actor,
            // Si un humano aprobó "crear nueva" pese a una supresión por nombre, ya confirmó el homónimo.
            confirmHomonym: $first->resolution === ImportResolution::CreateNew,
            source: DoctorSource::Import,
            importBatch: $batch,
            primarySpecialty: $specialtyIds !== [] ? Specialty::find($specialtyIds[0]) : null,
        );

        $profile = array_filter([
            'headline' => $group->value('headline'),
            'bio' => $group->value('bio'),
            'education' => $group->value('education'),
            'experience' => $group->value('experience'),
        ]);
        if ($profile !== []) {
            $this->updateDoctor->execute($doctor, $profile, $actor);
        }

        return $doctor;
    }

    /**
     * Ficha existente: completa lo vacío; lo distinto se anota como conflicto.
     *
     * @return array<int, array<string, mixed>> conflictos
     */
    private function fillMissing(Doctor $doctor, RowGroup $group, ?User $actor): array
    {
        $profile = $doctor->profile;
        $current = [
            ...$doctor->only(['professional_name', 'license_number']),
            'gender' => $doctor->gender?->value,
            ...($profile?->only(['headline', 'bio', 'education', 'experience']) ?? []),
        ];
        $incoming = [
            'professional_name' => $group->value('professional_name'),
            'gender' => CatalogResolver::gender($group->value('gender'))?->value,
            'license_number' => $group->first()->n_license,
            'headline' => $group->value('headline'),
            'bio' => $group->value('bio'),
            'education' => $group->value('education'),
            'experience' => $group->value('experience'),
        ];

        $fills = [];
        $conflicts = [];

        foreach (self::FILLABLE as $field) {
            $new = $incoming[$field];
            $old = $current[$field] ?? null;

            if ($new === null) {
                continue;
            }
            if (blank($old)) {
                $fills[$field] = $new;
            } elseif (Normalize::text((string) $old) !== Normalize::text($new)) {
                $conflicts[] = RowIssue::make(RowIssue::EXISTING_VALUE, "La ficha ya tiene otro valor en {$field} (\"{$old}\"); se conserva.", $field, $new, RowIssue::CONFLICT);
            }
        }

        foreach (['first_name' => 'n_first_name', 'last_name' => 'n_last_name'] as $field => $normalized) {
            $new = $group->first()->{$normalized};
            if ($new && Normalize::text($doctor->{$field}) !== Normalize::text($new)) {
                $conflicts[] = RowIssue::make(RowIssue::EXISTING_VALUE, "La ficha tiene {$field} \"{$doctor->{$field}}\" y el archivo \"{$new}\"; se conserva el de la ficha.", $field, $new, RowIssue::CONFLICT);
            }
        }

        if (isset($fills['license_number'])) {
            $fills['license_source'] = LicenseSource::ImportThirdParty->value;
        }

        if ($fills !== []) {
            try {
                $this->updateDoctor->execute($doctor, $fills, $actor);
            } catch (ValidationException $e) {
                $conflicts[] = RowIssue::make(RowIssue::EXISTING_VALUE, collect($e->errors())->flatten()->first(), 'license_number', null, RowIssue::CONFLICT);
            }
        }

        return $conflicts;
    }

    /**
     * Reutiliza la misma dirección si ya existe (la torre médica que comparten varios).
     */
    private function location(Doctor $doctor, ImportRow $row, ?User $actor): Location
    {
        $raw = $row->raw_payload;
        $type = CatalogResolver::locationType(RowGroup::clean($raw['location_type'] ?? null)) ?? LocationType::Office;

        $own = $doctor->locations()
            ->where('locations.city_id', $row->n_city_id)
            ->where('locations.address_normalized', $row->n_address)
            ->first();
        if ($own) {
            return $own;
        }

        $shared = Location::where('city_id', $row->n_city_id)
            ->where('address_normalized', $row->n_address)
            ->where('status', LocationStatus::Active)
            ->first();
        if ($shared) {
            $this->locations->attachExisting($doctor, $shared, $type, $actor);

            return $shared;
        }

        return $this->locations->attachNew($doctor, [
            'city_id' => $row->n_city_id,
            'name' => RowGroup::clean($raw['location_name'] ?? null),
            'address' => RowGroup::clean($raw['address'] ?? null),
            'address_2' => RowGroup::clean($raw['address_2'] ?? null),
            'postal_code' => RowGroup::clean($raw['postal_code'] ?? null),
        ], $type, $actor);
    }

    /**
     * Añade un contacto público si no existe ya. Un valor inválido se anota y se omite.
     *
     * @return array<int, array<string, mixed>>
     */
    private function addContact(Doctor $doctor, ContactType $type, string $value, ?Location $location, ?User $actor): array
    {
        if (in_array($type, [ContactType::Phone, ContactType::Mobile, ContactType::Whatsapp], true)) {
            $country = $location?->country?->code ?? $doctor->country->code;
            $normalized = PhoneNormalizer::toE164($value, $country);
            if ($normalized && $doctor->contacts()->where('type', $type)->where('value_normalized', $normalized)->exists()) {
                return [];
            }
        } elseif ($type === ContactType::Email && $doctor->contacts()->where('type', $type)->where('value_normalized', mb_strtolower(trim($value)))->exists()) {
            return [];
        }

        try {
            $this->contacts->save($doctor, null, [
                'type' => $type->value,
                'value' => $value,
                'location_id' => $location?->getKey(),
                'is_public' => true,
            ], $actor, ContactSource::Import);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();

            return str_contains($message, 'ya está registrado')
                ? []
                : [RowIssue::make(RowIssue::INVALID_PHONE, "{$type->getLabel()} omitido: {$message}", $type->value, $value, RowIssue::WARNING)];
        }

        return [];
    }

    /**
     * Guarda el ID del médico como referencia externa: la próxima carga con el mismo ID
     * reconoce la ficha (nivel 1 del matching) y la actualiza en vez de duplicarla.
     */
    private function rememberReference(RowGroup $group, ImportBatch $batch, Doctor $doctor): void
    {
        if (str_starts_with($group->ref, 'fila-')) {
            return;
        }

        $reference = DoctorExternalReference::firstOrNew(['source' => $batch->source, 'reference' => $group->ref]);
        $reference->forceFill([
            'doctor_id' => $doctor->getKey(),
            'first_seen_at' => $reference->first_seen_at ?? now(),
            'last_seen_at' => now(),
        ])->save();
    }

    private function markFailed(RowGroup $group, Throwable $e): void
    {
        foreach ($group->rows as $row) {
            // Primero se recarga: la transacción se revirtió, pero la instancia puede
            // conservar en memoria un "applied" que nunca llegó a la base.
            $row->refresh();

            if (in_array($row->status, self::APPLICABLE, true)) {
                $row->forceFill([
                    'status' => ImportRowStatus::Failed,
                    'validation_errors' => [...($row->validation_errors ?? []), RowIssue::make(RowIssue::APPLY_FAILED, 'No se pudo aplicar: '.$e->getMessage())],
                ])->save();
            }
        }
    }
}

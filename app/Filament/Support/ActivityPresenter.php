<?php

namespace App\Filament\Support;

use App\Domain\Directory\Enums\ContactType;
use App\Domain\Directory\Enums\DoctorGender;
use App\Domain\Directory\Enums\FacilitySector;
use App\Domain\Directory\Enums\FacilityType;
use App\Domain\Directory\Enums\LicenseSource;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Enums\VerificationSource;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Import\Enums\ImportResolution;
use App\Models\FacilityNetwork;
use Spatie\Activitylog\Models\Activity;

/**
 * Cómo se lee la auditoría en el backoffice: el nombre de cada evento, un resumen de una
 * línea a partir de sus `properties` y, cuando lo hay, el antes y después de cada campo.
 * Solo presentación: lo que se registra lo deciden las Actions (DATABASE.md §14.4).
 */
final class ActivityPresenter
{
    /**
     * Todos los eventos que registran las Actions, más los de §14.4 que aún no tienen
     * pantalla (fusión, reclamaciones). Un test comprueba que no falta ninguno.
     */
    public const EVENTS = [
        'doctor.created' => 'Ficha creada',
        'doctor.updated' => 'Ficha modificada',
        'doctor.published' => 'Ficha publicada',
        'doctor.unpublished' => 'Ficha despublicada',
        'doctor.verified' => 'Identidad verificada',
        'doctor.verification_rejected' => 'Verificación rechazada',
        'doctor.suspended' => 'Ficha suspendida',
        'doctor.suspension_lifted' => 'Suspensión levantada',
        'doctor.merged' => 'Fichas fusionadas',
        'facility.created' => 'Establecimiento creado',
        'facility.updated' => 'Establecimiento modificado',
        'facility.published' => 'Establecimiento activado',
        'facility.unpublished' => 'Establecimiento desactivado',
        'slug.updated' => 'Slug cambiado',
        'location.updated' => 'Ubicación modificada',
        'catalog.activated' => 'Activado',
        'catalog.deactivated' => 'Desactivado',
        'claim.submitted' => 'Reclamación enviada',
        'claim.approved' => 'Reclamación aprobada',
        'claim.rejected' => 'Reclamación rechazada',
        'user.suspended' => 'Cuenta suspendida',
        'user.reactivated' => 'Cuenta reactivada',
        'role.assigned' => 'Rol asignado',
        'role.removed' => 'Rol retirado',
        'import.ingested' => 'Lote cargado',
        'import.value_mapped' => 'Alias guardado desde un lote',
        'import.row_resolved' => 'Fila de lote resuelta',
        'import.applied' => 'Lote aplicado',
        'import.published' => 'Fichas del lote publicadas',
        'suppression.created' => 'Supresión registrada',
        'suppression.revoked' => 'Supresión revocada',
    ];

    /** Partes del agregado del médico (DoctorAggregateChange) y del establecimiento (FacilityAggregateChange). */
    private const PARTS = [
        'specialties' => 'Especialidades',
        'locations' => 'Ubicaciones',
        'contacts' => 'Contactos',
        'languages' => 'Idiomas',
    ];

    /** En un establecimiento, sus ubicaciones son sus sedes. */
    private const FACILITY_PARTS = [
        'locations' => 'Sedes',
    ];

    private const OPERATIONS = [
        'attached' => 'añadir',
        'attached_new' => 'añadir nueva',
        'attached_existing' => 'asociar existente',
        'detached' => 'quitar',
        'removed' => 'quitar',
        'created' => 'añadir',
        'updated' => 'modificar',
        'primary' => 'marcar como principal',
        'type' => 'cambiar tipo',
        'assigned' => 'asignar existente',
        'moved_in' => 'traer de otro establecimiento',
        'moved_out' => 'pasar a otro establecimiento',
    ];

    private const FIELDS = [
        'first_name' => 'Nombres',
        'last_name' => 'Apellidos',
        'professional_name' => 'Nombre profesional',
        'gender' => 'Género',
        'license_number' => 'Colegiado',
        'license_source' => 'Origen de la licencia',
        'verification_status' => 'Verificación',
        'verification_source' => 'Fuente de la verificación',
        'verified_at' => 'Verificada el',
        'verified_by_user_id' => 'Verificada por',
        'license_verified_at' => 'Licencia verificada el',
        'profile.headline' => 'Titular',
        'profile.bio' => 'Biografía',
        'profile.education' => 'Formación',
        'profile.experience' => 'Experiencia',
        'address' => 'Dirección',
        'address_2' => 'Complemento',
        'name' => 'Nombre',
        'city_id' => 'Ciudad',
        'postal_code' => 'Código postal',
        'latitude' => 'Latitud',
        'longitude' => 'Longitud',
        'type' => 'Tipo',
        'sector' => 'Sector',
        'network_id' => 'Red',
        'description' => 'Descripción',
        'logo_path' => 'Logo',
    ];

    public static function eventLabel(?string $event): string
    {
        return $event ? (self::EVENTS[$event] ?? $event) : '—';
    }

    /**
     * Una línea con lo esencial del evento, o null si el nombre del evento ya lo dice todo.
     */
    public static function summary(Activity $activity): ?string
    {
        $p = $activity->properties?->all() ?? [];

        if (isset($p['part'])) {
            $detail = match (true) {
                isset($p['type']) && $p['part'] === 'contacts' => ContactType::tryFrom($p['type'])?->getLabel(),
                isset($p['type']) && $p['part'] === 'locations' => LocationType::tryFrom($p['type'])?->getLabel(),
                default => $p['specialty'] ?? $p['language'] ?? null,
            };

            $parts = str_starts_with((string) $activity->event, 'facility.') ? [...self::PARTS, ...self::FACILITY_PARTS] : self::PARTS;

            return ($parts[$p['part']] ?? $p['part']).': '.(self::OPERATIONS[$p['op'] ?? ''] ?? ($p['op'] ?? 'cambio'))
                .($detail ? " ({$detail})" : '');
        }

        return match (true) {
            isset($p['fields']) => 'Campos: '.collect($p['fields'])
                ->reject(fn (string $f) => $f === 'name_normalized')
                ->map(fn (string $f) => self::FIELDS[$f] ?? $f)
                ->implode(', '),
            $activity->event === 'slug.updated' => ($p['old'] ?? '—').' → '.($p['new'] ?? '—'),
            isset($p['reason']) => 'Motivo: '.$p['reason'],
            isset($p['resolution']) => (ImportResolution::tryFrom($p['resolution'])?->getLabel() ?? $p['resolution'])
                .(isset($p['doctor_ref']) ? " · {$p['doctor_ref']}" : ''),
            isset($p['source']) && $activity->event === 'doctor.verified' => VerificationSource::tryFrom($p['source'])?->getLabel(),
            isset($p['role']) => "Rol: {$p['role']}",
            isset($p['text']) => "«{$p['text']}» → ".($p['city'] ?? $p['specialty'] ?? '—'),
            isset($p['published']) => "{$p['published']} publicadas, ".($p['skipped'] ?? 0).' sin publicar',
            isset($p['created']) => "{$p['created']} creadas, ".($p['updated'] ?? 0).' completadas, '.($p['failed'] ?? 0).' fallidas',
            default => null,
        };
    }

    /**
     * Antes y después de cada campo cambiado (attribute_changes en formato de spatie).
     *
     * @return array<int, array{field: string, old: string, new: string}>
     */
    public static function changes(Activity $activity): array
    {
        $changes = $activity->attribute_changes?->all() ?? [];
        $new = $changes['attributes'] ?? [];
        $old = $changes['old'] ?? [];

        return collect(array_unique([...array_keys($old), ...array_keys($new)]))
            ->map(fn (string $field) => [
                'field' => self::FIELDS[$field] ?? $field,
                'old' => self::value($field, $old[$field] ?? null),
                'new' => self::value($field, $new[$field] ?? null),
            ])
            ->values()
            ->all();
    }

    private static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $label = match ($field) {
            'gender' => DoctorGender::tryFrom((string) $value)?->getLabel(),
            'license_source' => LicenseSource::tryFrom((string) $value)?->getLabel(),
            'verification_status' => VerificationStatus::tryFrom((string) $value)?->getLabel(),
            'verification_source' => VerificationSource::tryFrom((string) $value)?->getLabel(),
            // 'type' solo aparece en attribute_changes de establecimientos.
            'type' => FacilityType::tryFrom((string) $value)?->getLabel(),
            'sector' => FacilitySector::tryFrom((string) $value)?->getLabel(),
            'network_id' => FacilityNetwork::whereKey($value)->value('name'),
            default => null,
        };

        return $label ?? (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

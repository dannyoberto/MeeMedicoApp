<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\ContactType;
use App\Domain\Directory\Enums\FacilityContactSource;
use App\Domain\Directory\Support\ContactNormalizer;
use App\Models\Facility;
use App\Models\FacilityContact;
use App\Models\User;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Contactos de un establecimiento: central, emergencias, citas (DATABASE.md §9.12).
 * Mismo patrón que DoctorContactsAction. Un contacto público es requisito para activarlo.
 * Si pertenece a una sede, esa sede es del propio establecimiento: lo garantiza esta
 * Action, no el motor.
 */
class FacilityContactsAction
{
    /**
     * @param  array{type: string|ContactType, value: string, label?: ?string, location_id?: ?string, is_public?: bool, is_primary?: bool}  $data
     *
     * @throws ValidationException (claves: value, location_id)
     */
    public function save(Facility $facility, ?FacilityContact $contact, array $data, ?User $actor, FacilityContactSource $source = FacilityContactSource::Admin): FacilityContact
    {
        $type = ContactType::from(enum_value($data['type']));
        $locationId = filled($data['location_id'] ?? null) ? $data['location_id'] : null;

        if ($locationId && ! $facility->locations()->whereKey($locationId)->exists()) {
            throw ValidationException::withMessages(['location_id' => 'Esa ubicación no es una sede de este establecimiento.']);
        }

        // Las sedes están en el país del establecimiento (FK compuesta): su código basta.
        $normalized = ContactNormalizer::normalize($type, (string) $data['value'], $facility->country()->value('code'));

        $duplicate = $facility->contacts()
            ->where('type', $type)
            ->where('value_normalized', $normalized)
            ->when($contact, fn ($q) => $q->whereKeyNot($contact->getKey()))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['value' => 'Ese contacto ya está registrado en este establecimiento.']);
        }

        try {
            return FacilityAggregateChange::apply($facility, $actor, ['part' => 'contacts', 'op' => $contact ? 'updated' : 'created', 'type' => $type->value], function () use ($facility, $contact, $data, $type, $normalized, $locationId, $source) {
                $contact ??= new FacilityContact(['facility_id' => $facility->getKey(), 'source' => $source]);

                $firstOfType = ! $facility->contacts()->where('type', $type)->when($contact->exists, fn ($q) => $q->whereKeyNot($contact->getKey()))->exists();
                $primary = ($data['is_primary'] ?? false) || $firstOfType;

                if ($primary) {
                    // Un principal por tipo (facility_contacts_one_primary_uniq): se desmarca el anterior primero.
                    $facility->contacts()->where('type', $type)->update(['is_primary' => false]);
                }

                $contact->fill([
                    'type' => $type,
                    'value' => trim((string) $data['value']),
                    'label' => filled($data['label'] ?? null) ? trim($data['label']) : null,
                    'location_id' => $locationId,
                    'is_public' => (bool) ($data['is_public'] ?? true),
                    'is_primary' => $primary,
                ]);
                $contact->value_normalized = $normalized;
                $contact->save();

                return $contact;
            });
        } catch (\Throwable $e) {
            // Transacción revertida: la instancia no debe conservar cambios que no están en la base.
            $contact?->exists && $contact->refresh();

            throw $e;
        }
    }

    public function remove(Facility $facility, FacilityContact $contact, ?User $actor): void
    {
        FacilityAggregateChange::apply($facility, $actor, ['part' => 'contacts', 'op' => 'removed', 'type' => $contact->type->value], function () use ($facility, $contact) {
            $type = $contact->type;
            $wasPrimary = $contact->is_primary;

            // Parte del agregado (CASCADE): un número equivocado se borra. Por consulta, para
            // que la instancia no quede marcada como borrada si el guardado revierte.
            FacilityContact::whereKey($contact->getKey())->delete();

            if ($wasPrimary) {
                $facility->contacts()->where('type', $type)->oldest()->first()?->update(['is_primary' => true]);
            }
        });
    }
}

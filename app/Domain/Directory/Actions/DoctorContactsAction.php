<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\ContactSource;
use App\Domain\Directory\Enums\ContactType;
use App\Domain\Directory\Support\ContactNormalizer;
use App\Domain\Directory\Support\SuppressionCheck;
use App\Models\Doctor;
use App\Models\DoctorContact;
use App\Models\User;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Contactos de un médico. En Fase 1 el paciente ve la ficha y llama: el contacto
 * público es requisito de publicación. value_normalized (E.164 para teléfonos,
 * minúsculas para correo) sirve para el enlace tel:/WhatsApp y para deduplicar.
 */
class DoctorContactsAction
{
    /**
     * @param  array{type: string, value: string, label?: ?string, location_id?: ?string, is_public?: bool, is_primary?: bool}  $data
     *
     * @throws ValidationException (claves: value, location_id)
     */
    public function save(Doctor $doctor, ?DoctorContact $contact, array $data, ?User $actor, ContactSource $source = ContactSource::Admin): DoctorContact
    {
        $type = ContactType::from(enum_value($data['type']));
        $locationId = $data['location_id'] ?? null;

        if ($locationId && ! $doctor->locations()->whereKey($locationId)->exists()) {
            throw ValidationException::withMessages(['location_id' => 'La ubicación no está asociada a esta ficha.']);
        }

        $normalized = $this->normalize($doctor, $type, (string) $data['value'], $locationId);

        // Un teléfono o correo de alguien que pidió no aparecer no vuelve a entrar (§14.1).
        // Solo un valor nuevo: el que ya estaba lo resuelve la puerta de publicación.
        $suppressed = match (true) {
            $contact?->value_normalized === $normalized => null,
            in_array($type, ContactNormalizer::PHONE_TYPES, true) => SuppressionCheck::strongMatch($doctor->country_id, null, [$normalized]),
            $type === ContactType::Email => SuppressionCheck::strongMatch($doctor->country_id, null, [], [$normalized]),
            default => null,
        };
        if ($suppressed) {
            throw ValidationException::withMessages([
                'value' => 'Este contacto es de una persona que pidió no aparecer en el directorio (supresión del '
                    .$suppressed->requested_at->format('d/m/Y').').',
            ]);
        }

        $duplicate = $doctor->contacts()
            ->where('type', $type)
            ->where('value_normalized', $normalized)
            ->when($contact, fn ($q) => $q->whereKeyNot($contact->getKey()))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['value' => 'Ese contacto ya está registrado en esta ficha.']);
        }

        $op = $contact ? 'updated' : 'created';

        try {
            return $this->persist($doctor, $contact, $data, $actor, $type, $normalized, $locationId, $source, $op);
        } catch (\Throwable $e) {
            // Transacción revertida: la instancia no debe conservar cambios que no están en la base.
            $contact?->exists && $contact->refresh();

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Doctor $doctor, ?DoctorContact $contact, array $data, ?User $actor, ContactType $type, string $normalized, ?string $locationId, ContactSource $source, string $op): DoctorContact
    {
        return DoctorAggregateChange::apply($doctor, $actor, ['part' => 'contacts', 'op' => $op, 'type' => $type->value], function () use ($doctor, $contact, $data, $type, $normalized, $locationId, $source) {
            $contact ??= new DoctorContact(['doctor_id' => $doctor->getKey(), 'source' => $source]);

            $firstOfType = ! $doctor->contacts()->where('type', $type)->when($contact->exists, fn ($q) => $q->whereKeyNot($contact->getKey()))->exists();
            $primary = ($data['is_primary'] ?? false) || $firstOfType;

            if ($primary) {
                // Un principal por tipo (doctor_contacts_one_primary_uniq): se desmarca el anterior primero.
                $doctor->contacts()->where('type', $type)->update(['is_primary' => false]);
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
    }

    public function remove(Doctor $doctor, DoctorContact $contact, ?User $actor): void
    {
        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'contacts', 'op' => 'removed', 'type' => $contact->type->value], function () use ($doctor, $contact) {
            $type = $contact->type;
            $wasPrimary = $contact->is_primary;

            // Un contacto es parte del agregado (CASCADE): un número equivocado se borra.
            // Por consulta y no con $contact->delete(): si el guardado de publicación
            // revierte la transacción, la instancia no debe quedar marcada como borrada.
            DoctorContact::whereKey($contact->getKey())->delete();

            if ($wasPrimary) {
                $doctor->contacts()->where('type', $type)->oldest()->first()?->update(['is_primary' => true]);
            }
        });
    }

    private function normalize(Doctor $doctor, ContactType $type, string $value, ?string $locationId): string
    {
        // El país del teléfono es el de su ubicación (consulta en otro país) o el del médico.
        $country = $locationId
            ? $doctor->locations()->whereKey($locationId)->first()?->country?->code
            : null;
        $country ??= $doctor->country()->value('code');

        return ContactNormalizer::normalize($type, $value, $country);
    }
}

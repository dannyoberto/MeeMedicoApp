<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Models\Doctor;
use App\Models\DoctorSuppression;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fichas existentes que coinciden con una supresión, en su país.
 *
 * Es la misma comparación que hará import:match antes de crear fichas (§12.3),
 * aplicada hacia atrás: la supresión impide que se creen, pero las que ya existen
 * hay que revisarlas a mano (despublicarlas es UnpublishDoctorAction, Etapa 3).
 */
final class SuppressionMatches
{
    /**
     * @return Collection<int, Doctor>
     */
    public static function for(DoctorSuppression $suppression): Collection
    {
        $contacts = array_filter([$suppression->phone_normalized, $suppression->email_normalized]);

        return Doctor::query()
            ->where('country_id', $suppression->country_id)
            ->where('status', '<>', DoctorStatus::Merged)
            ->where(function ($q) use ($suppression, $contacts) {
                $q->when($suppression->license_number, fn ($q, $license) => $q->orWhere('license_number', $license))
                    ->when($suppression->name_normalized, fn ($q, $name) => $q->orWhere('name_normalized', $name))
                    ->when($contacts, fn ($q) => $q->orWhereHas(
                        'contacts',
                        fn ($c) => $c->whereIn('value_normalized', $contacts),
                    ));
            })
            // Sin ninguna clave no hay coincidencias posibles (el CHECK lo impide, pero no se asume).
            ->when(! $suppression->license_number && ! $suppression->name_normalized && ! $contacts, fn ($q) => $q->whereRaw('false'))
            ->orderBy('last_name')
            ->get(['id', 'country_id', 'first_name', 'last_name', 'slug', 'status', 'license_number']);
    }
}

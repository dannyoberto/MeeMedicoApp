<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\LanguageStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\Language;
use App\Models\User;

/**
 * Idiomas que atiende un médico. No condicionan la publicación, pero se muestran en
 * la ficha pública: se auditan y purgan como el resto del agregado.
 */
class DoctorLanguagesAction
{
    public function attach(Doctor $doctor, Language $language, ?User $actor): void
    {
        if ($language->status !== LanguageStatus::Active) {
            throw new DirectoryRuleException("El idioma {$language->name} está inactivo.");
        }

        if ($doctor->languages()->whereKey($language->getKey())->exists()) {
            return;
        }

        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'languages', 'op' => 'attached', 'language' => $language->code],
            fn () => $doctor->languages()->attach($language->getKey()));
    }

    public function detach(Doctor $doctor, Language $language, ?User $actor): void
    {
        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'languages', 'op' => 'detached', 'language' => $language->code],
            fn () => $doctor->languages()->detach($language->getKey()));
    }
}

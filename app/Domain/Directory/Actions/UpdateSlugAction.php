<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Cdn\DoctorCachePurge;
use App\Domain\Directory\Enums\SlugRedirectEntity;
use App\Domain\Directory\Support\SlugRules;
use App\Models\Doctor;
use App\Models\SlugRedirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambia el slug público de una entidad sin romper URLs indexadas (AGENTS.md §3):
 * escribe slug_redirects ANTES de cambiar el slug, siempre en la misma transacción.
 */
class UpdateSlugAction
{
    /**
     * @throws ValidationException si el slug nuevo no es válido (clave: slug)
     */
    public function execute(Model $entity, string $newSlug): void
    {
        $oldSlug = $entity->getAttribute('slug');

        if ($newSlug === $oldSlug) {
            return;
        }

        $countryId = $entity->getAttribute('country_id');

        if ($violation = SlugRules::violation($entity::class, $newSlug, $countryId, $entity->getKey())) {
            throw ValidationException::withMessages(['slug' => $violation]);
        }

        $type = SlugRules::entityType($entity);
        $scope = SlugRules::countryScope($type, $countryId);

        // Un médico no puede quedarse con la URL antigua de OTRO médico: esa URL
        // indexada debe seguir llevando a su dueño. En catálogos el slug vivo prevalece.
        if ($entity instanceof Doctor) {
            $ownedByOther = SlugRedirect::query()
                ->where('entity_type', $type)
                ->where('country_id', $scope)
                ->where('old_slug', $newSlug)
                ->where('entity_id', '<>', $entity->getKey())
                ->exists();

            if ($ownedByOther) {
                throw ValidationException::withMessages(['slug' => 'Ese slug fue la URL de otro médico y sigue redirigiendo a su ficha.']);
            }

            DoctorCachePurge::around($entity, fn () => $this->apply($entity, $type, $scope, $oldSlug, $newSlug));

            return;
        }

        $this->apply($entity, $type, $scope, $oldSlug, $newSlug);
    }

    private function apply(Model $entity, SlugRedirectEntity $type, ?string $scope, string $oldSlug, string $newSlug): void
    {
        DB::transaction(function () use ($entity, $type, $scope, $oldSlug, $newSlug) {
            // El slug nuevo pasa a ser una URL viva: cualquier redirect que lo usara
            // (de esta entidad al volver a un nombre anterior, o de otra que lo dejó)
            // quedaría ambiguo o bloquearía un futuro redirect por slug_redirects_uniq.
            SlugRedirect::query()
                ->where('entity_type', $type)
                ->where('old_slug', $newSlug)
                ->where(fn ($q) => $scope === null ? $q->whereNull('country_id') : $q->where('country_id', $scope))
                ->delete();

            SlugRedirect::create([
                'entity_type' => $type,
                'entity_id' => $entity->getKey(),
                'country_id' => $scope,
                'old_slug' => $oldSlug,
            ]);

            $entity->forceFill(['slug' => $newSlug])->save();

            activity()
                ->performedOn($entity)
                ->event('slug.updated')
                ->withProperties(['old' => $oldSlug, 'new' => $newSlug])
                ->log('slug.updated');
        });
    }
}

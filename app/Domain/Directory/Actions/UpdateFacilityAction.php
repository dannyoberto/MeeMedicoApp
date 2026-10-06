<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\FacilitySector;
use App\Domain\Directory\Enums\FacilityType;
use App\Domain\Directory\Support\FacilityNetworkRules;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Edición de los datos de un establecimiento. NO cambia el slug (UpdateSlugAction deja
 * el 301) ni el país: sus sedes y su red están atadas a él por FK compuesta.
 */
class UpdateFacilityAction
{
    private const FIELDS = ['name', 'type', 'sector', 'network_id', 'description', 'logo_path'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException (claves: name, network_id)
     */
    public function execute(Facility $facility, array $data, ?User $actor): Facility
    {
        $fields = array_intersect_key($data, array_flip(self::FIELDS));

        if (array_key_exists('name', $fields)) {
            $fields['name'] = trim((string) $fields['name']);
            if ($fields['name'] === '') {
                throw ValidationException::withMessages(['name' => 'Indica el nombre del establecimiento.']);
            }
        }

        // Solo se valida la red si cambia: una red desactivada después no bloquea editar lo demás.
        if (array_key_exists('network_id', $fields)) {
            $fields['network_id'] = filled($fields['network_id']) ? $fields['network_id'] : null;
            if ($fields['network_id'] !== $facility->network_id) {
                FacilityNetworkRules::resolve($fields['network_id'], $facility->country_id);
            }
        }

        foreach (['type' => FacilityType::class, 'sector' => FacilitySector::class] as $key => $enum) {
            if (array_key_exists($key, $fields)) {
                $fields[$key] = $enum::from(enum_value($fields[$key]));
            }
        }

        if (array_key_exists('description', $fields)) {
            $fields['description'] = filled($fields['description']) ? trim($fields['description']) : null;
        }

        return DB::transaction(function () use ($facility, $fields, $actor) {
            $facility->fill($fields);

            $dirty = Arr::except($facility->getDirty(), ['updated_at']);
            $old = [];
            foreach (array_keys($dirty) as $key) {
                $old[$key] = $facility->getRawOriginal($key);
            }

            $facility->save();

            if ($dirty !== []) {
                // Antes y después de cada campo, para que la auditoría permita deshacer a mano.
                activity()
                    ->performedOn($facility)
                    ->causedBy($actor)
                    ->event('facility.updated')
                    ->withProperties(['fields' => array_keys($dirty)])
                    ->withChanges(['old' => $old, 'attributes' => $dirty])
                    ->log('facility.updated');
            }

            return $facility;
        });
    }
}

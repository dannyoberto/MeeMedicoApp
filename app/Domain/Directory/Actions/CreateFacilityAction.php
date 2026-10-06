<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\FacilitySector;
use App\Domain\Directory\Enums\FacilityType;
use App\Domain\Directory\Support\EntitySlugGenerator;
use App\Domain\Directory\Support\FacilityNetworkRules;
use App\Models\Country;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Alta de un establecimiento (DATABASE.md §9.10). Nace en borrador: activarlo es
 * PublishFacilityAction. El slug se genera único en su país y no se elige a mano.
 */
class CreateFacilityAction
{
    /**
     * @param  array{country_id: string, name: string, type: string|FacilityType, sector?: string|FacilitySector|null, network_id?: ?string, description?: ?string, logo_path?: ?string}  $data
     *
     * @throws ValidationException (claves: name, network_id, sector)
     */
    public function execute(array $data, ?User $actor): Facility
    {
        $country = Country::findOrFail($data['country_id']);
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Indica el nombre del establecimiento.']);
        }

        $network = FacilityNetworkRules::resolve($data['network_id'] ?? null, $country->getKey());

        // Sin sector explícito, el de su red: un hospital de la CCSS es público.
        $sector = filled($data['sector'] ?? null) ? FacilitySector::from(enum_value($data['sector'])) : $network?->sector;
        if (! $sector) {
            throw ValidationException::withMessages(['sector' => 'Indica si es público, privado o mixto.']);
        }

        return DB::transaction(function () use ($data, $country, $name, $network, $sector, $actor) {
            $facility = new Facility([
                'country_id' => $country->getKey(),
                'network_id' => $network?->getKey(),
                'name' => $name,
                'type' => FacilityType::from(enum_value($data['type'])),
                'sector' => $sector,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'logo_path' => $data['logo_path'] ?? null,
                'created_by_user_id' => $actor?->getKey(),
            ]);
            $facility->slug = EntitySlugGenerator::generate(Facility::class, $name, $country->getKey());
            $facility->save();

            activity()
                ->performedOn($facility)
                ->causedBy($actor)
                ->event('facility.created')
                ->withProperties(['slug' => $facility->slug])
                ->log('facility.created');

            return $facility;
        });
    }
}

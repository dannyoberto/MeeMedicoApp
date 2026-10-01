<?php

namespace App\Domain\Import\Actions;

use App\Domain\Directory\Enums\SpecialtyAliasKind;
use App\Domain\Geo\Enums\CityAliasSource;
use App\Domain\Geo\Support\Normalize;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\ImportBatch;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use App\Models\User;
use InvalidArgumentException;

/**
 * "Esta ciudad escrita así es Escazú." Graba el alias para que la próxima carga lo
 * resuelva sola (§12.3: cada lote debe requerir menos intervención que el anterior).
 * Después hay que volver a normalizar y buscar coincidencias en el lote.
 */
class MapImportValueAction
{
    public function city(ImportBatch $batch, string $text, City $city, User $actor): void
    {
        if ($city->country_id !== $batch->country_id) {
            throw new InvalidArgumentException('La ciudad debe ser del país del lote.');
        }

        CityAlias::firstOrCreate(
            ['country_id' => $batch->country_id, 'alias_normalized' => Normalize::text($text)],
            ['city_id' => $city->getKey(), 'alias' => $text, 'source' => CityAliasSource::Admin],
        );

        activity()->performedOn($batch)->causedBy($actor)->event('import.value_mapped')
            ->withProperties(['kind' => 'city', 'text' => $text, 'city' => $city->slug])->log('import.value_mapped');
    }

    public function specialty(ImportBatch $batch, string $text, Specialty $specialty, User $actor): void
    {
        SpecialtyAlias::firstOrCreate(
            ['alias_normalized' => Normalize::text($text)],
            ['specialty_id' => $specialty->getKey(), 'alias' => $text, 'kind' => SpecialtyAliasKind::Import],
        );

        activity()->performedOn($batch)->causedBy($actor)->event('import.value_mapped')
            ->withProperties(['kind' => 'specialty', 'text' => $text, 'specialty' => $specialty->slug])->log('import.value_mapped');
    }
}

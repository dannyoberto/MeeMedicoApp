<?php

namespace App\Domain\Directory\Support;

use App\Domain\Directory\Enums\FacilityNetworkStatus;
use App\Models\FacilityNetwork;
use Illuminate\Validation\ValidationException;

/**
 * Validación de la red de un establecimiento, compartida por el alta y la edición.
 * La FK compuesta ya impide una red de otro país (§9.10); esto da el mensaje legible antes.
 */
final class FacilityNetworkRules
{
    /**
     * @throws ValidationException (clave: network_id)
     */
    public static function resolve(?string $networkId, string $countryId): ?FacilityNetwork
    {
        if (blank($networkId)) {
            return null;
        }

        $network = FacilityNetwork::find($networkId);

        if (! $network || $network->country_id !== $countryId) {
            throw ValidationException::withMessages(['network_id' => 'La red debe ser del mismo país que el establecimiento.']);
        }

        if ($network->status !== FacilityNetworkStatus::Active) {
            throw ValidationException::withMessages(['network_id' => "La red {$network->name} está inactiva."]);
        }

        return $network;
    }
}

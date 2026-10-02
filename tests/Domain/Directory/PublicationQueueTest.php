<?php

use App\Domain\Directory\Actions\DoctorContactsAction;
use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Support\PublicationRequirements;
use App\Models\Doctor;
use Illuminate\Support\Facades\DB;

/*
| Las colas "Listos para publicar" / "Incompletos" del backoffice consultan la misma
| regla que PublishDoctorAction. Si alguna vez divergen, este test lo detecta.
*/

it('whereReady y whereNotReady coinciden con check() ficha por ficha', function () {
    $complete = publishableDoctor(['first_name' => 'Completa']);

    $privateContact = makeDoctor(['first_name' => 'Privado']);
    app(DoctorSpecialtiesAction::class)->attach($privateContact, specialty(), null);
    app(DoctorLocationsAction::class)->attachNew($privateContact, ['city_id' => city()->id, 'address' => 'Calle 1'], LocationType::Office, null);
    app(DoctorContactsAction::class)->save($privateContact, null, ['type' => 'phone', 'value' => '2222-4444', 'is_public' => false], null);

    $inactiveSpecialty = publishableDoctor(['first_name' => 'Especialidad']);
    DB::table('doctor_specialties')->where('doctor_id', $inactiveSpecialty->id)->update(['specialty_id' => specialty('pediatria')->id]);
    DB::table('specialties')->where('slug', 'pediatria')->update(['status' => 'inactive']);

    $inactiveCity = publishableDoctor(['first_name' => 'Ciudad']);
    $otherCity = city('CR', 'Santa Ana');
    DB::table('locations')->whereIn('id', $inactiveCity->locations()->pluck('locations.id'))->update(['city_id' => $otherCity->id, 'region_id' => $otherCity->region_id]);
    DB::table('cities')->where('id', $otherCity->id)->update(['status' => 'inactive']);

    $noPrimaryLocation = publishableDoctor(['first_name' => 'Sin principal']);
    DB::table('doctor_locations')->where('doctor_id', $noPrimaryLocation->id)->update(['is_primary' => false]);

    $empty = makeDoctor(['first_name' => 'Vacía']);

    $ready = PublicationRequirements::whereReady(Doctor::query())->pluck('id')->all();
    $notReady = PublicationRequirements::whereNotReady(Doctor::query())->pluck('id')->all();

    foreach ([$complete, $privateContact, $inactiveSpecialty, $inactiveCity, $noPrimaryLocation, $empty] as $doctor) {
        $meets = ! in_array(false, PublicationRequirements::check($doctor->refresh()), true);

        expect(in_array($doctor->id, $ready, true))->toBe($meets, "whereReady discrepa en {$doctor->first_name}")
            ->and(in_array($doctor->id, $notReady, true))->toBe(! $meets, "whereNotReady discrepa en {$doctor->first_name}");
    }

    expect($ready)->toBe([$complete->id]);
});

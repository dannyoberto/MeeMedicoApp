<?php

use App\Domain\Directory\Actions\DoctorContactsAction;
use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Actions\SaveLocationAction;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use Illuminate\Validation\ValidationException;

describe('especialidades', function () {
    it('la primera es la principal y cambiar la principal deja exactamente una', function () {
        $doctor = makeDoctor();
        $action = app(DoctorSpecialtiesAction::class);
        $action->attach($doctor, specialty(), null);
        $action->attach($doctor, specialty('pediatria'), null);

        expect($doctor->specialties()->wherePivot('is_primary', true)->pluck('slug')->all())->toBe(['cardiologia']);

        $action->makePrimary($doctor, specialty('pediatria'), null);
        expect($doctor->specialties()->wherePivot('is_primary', true)->pluck('slug')->all())->toBe(['pediatria']);
    });

    it('quitar la principal promueve otra', function () {
        $doctor = makeDoctor();
        $action = app(DoctorSpecialtiesAction::class);
        $action->attach($doctor, specialty(), null);
        $action->attach($doctor, specialty('pediatria'), null);
        $action->detach($doctor, specialty(), null);

        expect($doctor->specialties()->wherePivot('is_primary', true)->pluck('slug')->all())->toBe(['pediatria']);
    });

    it('no asigna una especialidad inactiva ni la misma dos veces', function () {
        $doctor = makeDoctor();
        app(DoctorSpecialtiesAction::class)->attach($doctor, specialty(), null);
        specialty('pediatria')->forceFill(['status' => 'inactive'])->save();

        expect(fn () => app(DoctorSpecialtiesAction::class)->attach($doctor, specialty(), null))->toThrow(DirectoryRuleException::class)
            ->and(fn () => app(DoctorSpecialtiesAction::class)->attach($doctor, specialty('pediatria'), null))->toThrow(DirectoryRuleException::class);
    });
});

describe('ubicaciones', function () {
    it('deriva país y región de la ciudad y normaliza la dirección', function () {
        $city = city();
        $location = app(DoctorLocationsAction::class)->attachNew(makeDoctor(), ['city_id' => $city->id, 'address' => 'Av. Central #123'], LocationType::Office, null);

        expect($location->country_id)->toBe($city->country_id)
            ->and($location->region_id)->toBe($city->region_id)
            ->and($location->address_normalized)->toBe('av. central #123');
    });

    it('una ubicación compartida se desasocia de una ficha sin borrarse', function () {
        $a = makeDoctor();
        $b = makeDoctor(['first_name' => 'Luis']);
        $location = app(DoctorLocationsAction::class)->attachNew($a, ['city_id' => city()->id, 'address' => 'Torre 1'], LocationType::Office, null);
        app(DoctorLocationsAction::class)->attachExisting($b, $location, LocationType::Clinic, null);

        app(DoctorLocationsAction::class)->detach($a, $location, null);

        expect($location->fresh())->not->toBeNull()
            ->and($location->doctors()->count())->toBe(1);
    });

    it('exige latitud y longitud juntas', function () {
        expect(fn () => app(SaveLocationAction::class)->create(['city_id' => city()->id, 'address' => 'x', 'latitude' => 9.9], null))
            ->toThrow(ValidationException::class);
    });
});

describe('contactos', function () {
    it('normaliza teléfonos a E.164 con el país del médico', function () {
        $contact = app(DoctorContactsAction::class)->save(makeDoctor(), null, ['type' => 'phone', 'value' => '2222-3333'], null);

        expect($contact->value_normalized)->toBe('+50622223333');
    });

    it('usa el país de la ubicación para un teléfono de consulta en otro país', function () {
        $doctor = makeDoctor();
        $rd = app(DoctorLocationsAction::class)->attachNew($doctor, ['city_id' => city('DO', 'Santo Domingo')->id, 'address' => 'Av. Churchill'], LocationType::Clinic, null);

        expect(fn () => app(DoctorContactsAction::class)->save($doctor, null, ['type' => 'whatsapp', 'value' => '(829) 555-1234'], null))
            ->toThrow(ValidationException::class);

        $contact = app(DoctorContactsAction::class)->save($doctor, null, ['type' => 'whatsapp', 'value' => '(829) 555-1234', 'location_id' => $rd->id], null);
        expect($contact->value_normalized)->toBe('+18295551234');
    });

    it('rechaza valores inválidos y duplicados', function (array $data) {
        $doctor = makeDoctor();
        app(DoctorContactsAction::class)->save($doctor, null, ['type' => 'phone', 'value' => '2222-3333'], null);

        expect(fn () => app(DoctorContactsAction::class)->save($doctor, null, $data, null))->toThrow(ValidationException::class);
    })->with([
        'teléfono inválido' => [['type' => 'phone', 'value' => '123']],
        'correo inválido' => [['type' => 'email', 'value' => 'no-es-correo']],
        'web inválida' => [['type' => 'website', 'value' => 'localhost']],
        'duplicado con otro formato' => [['type' => 'phone', 'value' => '+506 2222 3333']],
    ]);

    it('normaliza la web con esquema y host en minúsculas', function () {
        $contact = app(DoctorContactsAction::class)->save(makeDoctor(), null, ['type' => 'website', 'value' => 'WWW.Clinica.COM/Dr/'], null);

        expect($contact->value_normalized)->toBe('https://www.clinica.com/Dr');
    });

    it('mantiene un solo principal por tipo', function () {
        $doctor = makeDoctor();
        app(DoctorContactsAction::class)->save($doctor, null, ['type' => 'phone', 'value' => '2222-3333'], null);
        $second = app(DoctorContactsAction::class)->save($doctor, null, ['type' => 'phone', 'value' => '4000-1234', 'is_primary' => true], null);

        expect($doctor->contacts()->where('is_primary', true)->pluck('id')->all())->toBe([$second->id]);
    });
});

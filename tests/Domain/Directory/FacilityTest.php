<?php

use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Actions\FacilityContactsAction;
use App\Domain\Directory\Actions\FacilityLocationsAction;
use App\Domain\Directory\Actions\PublishFacilityAction;
use App\Domain\Directory\Actions\SaveLocationAction;
use App\Domain\Directory\Actions\SetCatalogStatusAction;
use App\Domain\Directory\Actions\UnpublishFacilityAction;
use App\Domain\Directory\Actions\UpdateFacilityAction;
use App\Domain\Directory\Actions\UpdateSlugAction;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\FacilityNetwork;
use App\Models\Location;
use App\Models\SlugRedirect;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| Establecimientos (DATABASE.md §9.9–§9.12, MODULO-ESTABLECIMIENTOS-SEGUROS.md §5–§6):
| el establecimiento es dueño de sus sedes, sus médicos se derivan de ellas, y uno activo
| nunca queda sin sede activa ni contacto público.
*/

function network(string $countryCode = 'CR', array $attrs = []): FacilityNetwork
{
    return FacilityNetwork::create(['country_id' => country($countryCode)->id, 'name' => "Red {$countryCode}", 'sector' => 'public', ...$attrs]);
}

function looseLocation(string $countryCode = 'CR', string $address = 'Torre Médica, piso 4'): Location
{
    return app(SaveLocationAction::class)->create(['city_id' => city($countryCode, $countryCode === 'CR' ? 'Escazú' : 'Mixco')->id, 'address' => $address], null);
}

describe('alta y edición', function () {
    it('nace en borrador, con slug único por país, y lo audita', function () {
        $first = makeFacility(['name' => 'Hospital México']);
        $second = makeFacility(['name' => 'Hospital México']);
        $otherCountry = makeFacility(['name' => 'Hospital México', 'country_id' => country('GT')->id]);

        expect($first->status->value)->toBe('draft')
            ->and($first->slug)->toBe('hospital-mexico')
            ->and($second->slug)->toBe('hospital-mexico-2')
            ->and($otherCountry->slug)->toBe('hospital-mexico')
            ->and(Activity::where('subject_id', $first->id)->where('event', 'facility.created')->exists())->toBeTrue();
    });

    it('hereda el sector de su red si no se indica', function () {
        $facility = makeFacility(['network_id' => network()->id, 'sector' => null]);

        expect($facility->sector->value)->toBe('public');
    });

    it('exige sector cuando no hay red', function () {
        expect(fn () => makeFacility(['sector' => null]))->toThrow(ValidationException::class);
    });

    it('rechaza una red de otro país o inactiva', function () {
        expect(fn () => makeFacility(['network_id' => network('GT')->id]))->toThrow(ValidationException::class)
            ->and(fn () => makeFacility(['network_id' => network('CR', ['name' => 'Inactiva', 'status' => 'inactive'])->id]))->toThrow(ValidationException::class);
    });

    it('edita los datos con auditoría de antes y después, sin tocar el slug', function () {
        $facility = makeFacility();

        app(UpdateFacilityAction::class)->execute($facility, ['name' => 'Hospital San Rafael Arcángel', 'type' => 'clinic'], null);

        $changes = Activity::where('subject_id', $facility->id)->where('event', 'facility.updated')->latest('id')->firstOrFail()->attribute_changes;
        expect($facility->fresh()->slug)->toBe('hospital-san-rafael')
            ->and($changes['old'])->toMatchArray(['name' => 'Hospital San Rafael', 'type' => 'hospital'])
            ->and($changes['attributes'])->toMatchArray(['name' => 'Hospital San Rafael Arcángel', 'type' => 'clinic']);
    });

    it('una red desactivada después no impide editar lo demás', function () {
        $net = network();
        $facility = makeFacility(['network_id' => $net->id]);
        app(SetCatalogStatusAction::class)->execute($net, false, null);

        app(UpdateFacilityAction::class)->execute($facility, ['description' => 'Atención 24 horas'], null);

        expect($facility->fresh()->description)->toBe('Atención 24 horas');
    });
});

describe('slug', function () {
    it('cambiar el slug deja el 301', function () {
        $facility = makeFacility();

        app(UpdateSlugAction::class)->execute($facility, 'hospital-sr');

        expect($facility->fresh()->slug)->toBe('hospital-sr')
            ->and(SlugRedirect::where('entity_type', 'facility')->where('entity_id', $facility->id)->value('old_slug'))->toBe('hospital-san-rafael');
    });

    it('no se queda con la URL antigua de otro establecimiento, ni al generarse ni al editarse', function () {
        $old = makeFacility();
        app(UpdateSlugAction::class)->execute($old, 'hospital-sr');

        $new = makeFacility();
        expect($new->slug)->toBe('hospital-san-rafael-2')
            ->and(fn () => app(UpdateSlugAction::class)->execute($new, 'hospital-san-rafael'))->toThrow(ValidationException::class);
    });
});

describe('sedes', function () {
    it('crea una sede en su país y los médicos que atienden en ella son del establecimiento', function () {
        $facility = makeFacility();
        $sede = app(FacilityLocationsAction::class)->create($facility, ['city_id' => city()->id, 'address' => 'Calle 1'], null);
        $doctor = makeDoctor();
        app(DoctorLocationsAction::class)->attachExisting($doctor, $sede, LocationType::Hospital, null);

        expect($sede->facility_id)->toBe($facility->id)
            ->and($facility->doctors()->pluck('id')->all())->toBe([$doctor->id]);
    });

    it('no crea una sede en otro país', function () {
        expect(fn () => app(FacilityLocationsAction::class)->create(makeFacility(), ['city_id' => city('GT', 'Mixco')->id, 'address' => 'x'], null))
            ->toThrow(ValidationException::class);
    });

    it('asigna una ubicación suelta, pero no una de otro país ni la sede de otro establecimiento', function () {
        $facility = makeFacility();
        $other = makeFacility(['name' => 'Clínica Otra']);
        $taken = looseLocation(address: 'Otra dirección');
        app(FacilityLocationsAction::class)->assign($other, $taken, null);

        app(FacilityLocationsAction::class)->assign($facility, $loose = looseLocation(), null);

        expect($loose->fresh()->facility_id)->toBe($facility->id)
            ->and(fn () => app(FacilityLocationsAction::class)->assign($facility, looseLocation('GT'), null))->toThrow(DirectoryRuleException::class)
            ->and(fn () => app(FacilityLocationsAction::class)->assign($facility, $taken->fresh(), null))->toThrow(DirectoryRuleException::class, 'Mover');
    });

    it('mover una sede deja sus contactos al establecimiento de origen como generales', function () {
        $from = makeFacility(['name' => 'Origen']);
        $to = makeFacility(['name' => 'Destino']);
        $sede = app(FacilityLocationsAction::class)->create($from, ['city_id' => city()->id, 'address' => 'Calle 1'], null);
        $contact = app(FacilityContactsAction::class)->save($from, null, ['type' => 'phone', 'value' => '2222-5555', 'location_id' => $sede->id], null);

        app(FacilityLocationsAction::class)->move($to, $sede->fresh(), null);

        expect($sede->fresh()->facility_id)->toBe($to->id)
            ->and($contact->fresh()->location_id)->toBeNull()
            ->and($contact->fresh()->facility_id)->toBe($from->id);
    });

    it('no mueve la única sede de un establecimiento activo', function () {
        $from = activeFacility(['name' => 'Origen']);
        $sede = $from->locations()->first();

        expect(fn () => app(FacilityLocationsAction::class)->move(makeFacility(['name' => 'Destino']), $sede, null))->toThrow(DirectoryRuleException::class)
            ->and($sede->fresh()->facility_id)->toBe($from->id);
    });

    it('quitar una sede no la borra ni desvincula a sus médicos', function () {
        $facility = makeFacility();
        $sede = app(FacilityLocationsAction::class)->create($facility, ['city_id' => city()->id, 'address' => 'Calle 1'], null);
        app(DoctorLocationsAction::class)->attachExisting($doctor = makeDoctor(), $sede, LocationType::Office, null);

        app(FacilityLocationsAction::class)->detach($facility, $sede->fresh(), null);

        expect($sede->fresh()->facility_id)->toBeNull()
            ->and($doctor->locations()->whereKey($sede->id)->exists())->toBeTrue()
            ->and($facility->doctors()->count())->toBe(0);
    });

    it('no quita la única sede de un establecimiento activo', function () {
        $facility = activeFacility();

        expect(fn () => app(FacilityLocationsAction::class)->detach($facility, $facility->locations()->first(), null))->toThrow(DirectoryRuleException::class);
    });

    it('no desactiva la única sede activa de un establecimiento activo', function () {
        $facility = activeFacility();

        expect(fn () => app(SetCatalogStatusAction::class)->execute($facility->locations()->first(), false, null))->toThrow(DirectoryRuleException::class);
    });
});

describe('contactos', function () {
    it('normaliza a E.164 con el país del establecimiento y el primero de cada tipo es principal', function () {
        $facility = makeFacility();
        $central = app(FacilityContactsAction::class)->save($facility, null, ['type' => 'phone', 'value' => '2222-3333', 'label' => 'Central'], null);
        $emergencias = app(FacilityContactsAction::class)->save($facility, null, ['type' => 'phone', 'value' => '2222-9999', 'is_primary' => true], null);

        expect($central->value_normalized)->toBe('+50622223333')
            ->and($central->fresh()->is_primary)->toBeFalse()
            ->and($emergencias->is_primary)->toBeTrue();
    });

    it('rechaza un duplicado y una sede de otro establecimiento', function () {
        $facility = makeFacility();
        app(FacilityContactsAction::class)->save($facility, null, ['type' => 'email', 'value' => 'Citas@Hospital.cr'], null);
        $foreign = app(FacilityLocationsAction::class)->create(makeFacility(['name' => 'Otra']), ['city_id' => city()->id, 'address' => 'x'], null);

        expect(fn () => app(FacilityContactsAction::class)->save($facility, null, ['type' => 'email', 'value' => 'citas@hospital.cr'], null))->toThrow(ValidationException::class)
            ->and(fn () => app(FacilityContactsAction::class)->save($facility, null, ['type' => 'phone', 'value' => '2222-1111', 'location_id' => $foreign->id], null))->toThrow(ValidationException::class);
    });

    it('no deja a un establecimiento activo sin contacto público', function () {
        $facility = activeFacility();
        $contact = $facility->contacts()->first();

        expect(fn () => app(FacilityContactsAction::class)->remove($facility, $contact, null))->toThrow(DirectoryRuleException::class)
            ->and(fn () => app(FacilityContactsAction::class)->save($facility, $contact, ['type' => 'phone', 'value' => '2222-4444', 'is_public' => false], null))->toThrow(DirectoryRuleException::class)
            ->and($contact->fresh()->is_public)->toBeTrue();
    });
});

describe('activación', function () {
    it('no activa sin sede activa ni contacto público, y lista lo que falta', function () {
        $facility = makeFacility();

        try {
            app(PublishFacilityAction::class)->execute($facility, null);
            $this->fail('Debió rechazarse');
        } catch (DirectoryRuleException $e) {
            expect($e->details)->toHaveCount(2);
        }

        expect($facility->fresh()->status->value)->toBe('draft');
    });

    it('activa un establecimiento completo y lo audita', function () {
        $facility = activeFacility();

        expect($facility->status->value)->toBe('active')
            ->and(Activity::where('subject_id', $facility->id)->where('event', 'facility.published')->exists())->toBeTrue();
    });

    it('desactivar conserva sedes y contactos, y deja quitar la última sede', function () {
        $facility = activeFacility();

        app(UnpublishFacilityAction::class)->execute($facility, null, 'Cerró');
        app(FacilityLocationsAction::class)->detach($facility, $facility->locations()->first(), null);

        expect($facility->fresh()->status->value)->toBe('inactive')
            ->and($facility->contacts()->count())->toBe(1)
            ->and(Activity::where('subject_id', $facility->id)->where('event', 'facility.unpublished')->value('properties')['reason'])->toBe('Cerró');
    });
});

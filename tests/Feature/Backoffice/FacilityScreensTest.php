<?php

use App\Domain\Directory\Actions\FacilityContactsAction;
use App\Domain\Directory\Actions\FacilityLocationsAction;
use App\Domain\Directory\Actions\PublishDoctorAction;
use App\Domain\Directory\Actions\SaveLocationAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Filament\Resources\Facilities\Pages\CreateFacility;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Facilities\Pages\ListFacilities;
use App\Filament\Resources\Facilities\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\DoctorsRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\LocationsRelationManager;
use App\Filament\Resources\FacilityNetworks\Pages\ManageFacilityNetworks;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Facility;
use App\Models\FacilityNetwork;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

/*
| Pantallas de establecimientos y redes (MODULO-ESTABLECIMIENTOS-SEGUROS.md §7). Las reglas
| se prueban en tests/Domain/Directory/FacilityTest.php; aquí, que cada pantalla llega a ellas.
| Los formularios usan set() y mountAction() (fillForm no dispara afterStateUpdated en Filament 5).
*/

beforeEach(function () {
    Bus::fake([PurgeCdnPaths::class]);
    $this->actingAs(admin());
});

function facilityTab(string $class, Facility $facility)
{
    return Livewire::test($class, ['ownerRecord' => $facility->fresh(), 'pageClass' => EditFacility::class]);
}

it('el operador crea, completa y activa un establecimiento de principio a fin', function () {
    Livewire::test(CreateFacility::class)
        ->set('data.country_id', country()->id)
        ->set('data.name', 'Hospital Clínica Bíblica')
        ->set('data.type', 'hospital')
        ->set('data.sector', 'private')
        ->call('create')
        ->assertHasNoErrors();

    $facility = Facility::where('slug', 'hospital-clinica-biblica')->firstOrFail();
    expect($facility->status->value)->toBe('draft');

    facilityTab(LocationsRelationManager::class, $facility)->mountTableAction('createSede')
        ->set('mountedActions.0.data.city_id', city()->id)
        ->set('mountedActions.0.data.address', 'Av. 14, calles central y 1')
        ->callMountedTableAction()->assertHasNoErrors();

    facilityTab(ContactsRelationManager::class, $facility)->mountTableAction('addContact')
        ->set('mountedActions.0.data.type', 'phone')->set('mountedActions.0.data.value', '2522-1000')
        ->set('mountedActions.0.data.label', 'Central')->set('mountedActions.0.data.is_public', true)
        ->callMountedTableAction()->assertHasNoErrors();

    $page = Livewire::test(EditFacility::class, ['record' => $facility->id]);
    expect(substr_count($page->html(), 'text-success-600'))->toBe(2);

    $page->callAction('publish');
    expect($facility->fresh()->status->value)->toBe('active');
});

it('elegir una red propone su sector y solo ofrece redes del país', function () {
    $ccss = FacilityNetwork::create(['country_id' => country()->id, 'name' => 'CCSS', 'sector' => 'public']);
    $igss = FacilityNetwork::create(['country_id' => country('GT')->id, 'name' => 'IGSS', 'sector' => 'public']);

    Livewire::test(CreateFacility::class)
        ->set('data.country_id', country()->id)
        ->set('data.name', 'Hospital México')
        ->set('data.type', 'hospital')
        ->set('data.network_id', $ccss->id)
        ->assertSet('data.sector', 'public')
        ->set('data.network_id', $igss->id)
        ->call('create')
        ->assertHasErrors(['data.network_id']);

    expect(Facility::count())->toBe(0);
});

it('la red se crea en un modal y no se repite en el mismo país', function () {
    Livewire::test(ManageFacilityNetworks::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.country_id', country()->id)
        ->set('mountedActions.0.data.name', 'Caja Costarricense de Seguro Social')
        ->set('mountedActions.0.data.short_name', 'CCSS')
        ->set('mountedActions.0.data.sector', 'public')
        ->callMountedAction()->assertHasNoErrors();

    Livewire::test(ManageFacilityNetworks::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.country_id', country()->id)
        ->set('mountedActions.0.data.name', 'Caja Costarricense de Seguro Social')
        ->set('mountedActions.0.data.sector', 'public')
        ->callMountedAction()->assertHasErrors(['mountedActions.0.data.name']);

    expect(FacilityNetwork::where('short_name', 'CCSS')->count())->toBe(1);
});

describe('sedes', function () {
    it('convierte en sede una dirección suelta y trae la de otro establecimiento', function () {
        $facility = makeFacility();
        $loose = app(SaveLocationAction::class)->create(['city_id' => city()->id, 'name' => 'Torre Médica', 'address' => 'Calle 5'], null);
        $other = makeFacility(['name' => 'Clínica Vecina']);
        $foreign = app(FacilityLocationsAction::class)->create($other, ['city_id' => city()->id, 'address' => 'Calle 9'], null);

        facilityTab(LocationsRelationManager::class, $facility)->mountTableAction('assignSede')
            ->set('mountedActions.0.data.location_id', $loose->id)->callMountedTableAction()->assertHasNoErrors();
        facilityTab(LocationsRelationManager::class, $facility)->mountTableAction('moveSede')
            ->set('mountedActions.0.data.location_id', $foreign->id)->callMountedTableAction()->assertHasNoErrors();

        expect($facility->locations()->pluck('id')->sort()->values()->all())->toBe(collect([$loose->id, $foreign->id])->sort()->values()->all());
    });

    it('avisa y no quita la única sede de un establecimiento activo', function () {
        $facility = activeFacility();

        facilityTab(LocationsRelationManager::class, $facility)
            ->callTableAction('detachSede', $facility->locations()->first())
            ->assertNotified();

        expect($facility->locations()->count())->toBe(1);
    });
});

describe('médicos', function () {
    it('asocia un médico a una sede con la modalidad que propone el tipo, y lo lista', function () {
        $facility = activeFacility();
        $doctor = makeDoctor();

        facilityTab(DoctorsRelationManager::class, $facility)->mountTableAction('attachDoctor')
            ->assertSet('mountedActions.0.data.location_type', 'hospital')
            ->assertSet('mountedActions.0.data.location_id', $facility->locations()->value('id'))
            ->set('mountedActions.0.data.doctor_id', $doctor->id)
            ->callMountedTableAction()->assertHasNoErrors();

        facilityTab(DoctorsRelationManager::class, $facility)
            ->assertCanSeeTableRecords([$doctor])
            ->assertSeeText('Calle 1, Av. 2 · Hospital');
    });

    it('desvincula al médico de las sedes del establecimiento, no de las demás', function () {
        $facility = activeFacility();
        $doctor = publishedDoctor();
        facilityTab(DoctorsRelationManager::class, $facility)->mountTableAction('attachDoctor')
            ->set('mountedActions.0.data.doctor_id', $doctor->id)->callMountedTableAction();

        facilityTab(DoctorsRelationManager::class, $facility)->callTableAction('detachDoctor', $doctor);

        expect($facility->doctors()->count())->toBe(0)
            ->and($doctor->locations()->count())->toBe(1);
    });

    it('no deja sin ubicación a una ficha publicada cuya única sede es del establecimiento', function () {
        $facility = activeFacility();
        $doctor = publishableDoctor();
        $doctor->locations()->detach();
        facilityTab(DoctorsRelationManager::class, $facility)->mountTableAction('attachDoctor')
            ->set('mountedActions.0.data.doctor_id', $doctor->id)->callMountedTableAction();
        app(PublishDoctorAction::class)->execute($doctor->fresh(), null);

        facilityTab(DoctorsRelationManager::class, $facility)->callTableAction('detachDoctor', $doctor)->assertNotified();

        expect($facility->doctors()->count())->toBe(1);
    });
});

describe('listados', function () {
    it('cuenta sedes y médicos, busca sin acentos y separa por estado', function () {
        $active = activeFacility(['name' => 'Clínica Bíblica']);
        $draft = makeFacility(['name' => 'Hospital México']);

        // La búsqueda se recuerda en la sesión: las pestañas se comprueban antes.
        Livewire::test(ListFacilities::class)
            ->set('activeTab', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$active]);

        Livewire::test(ListFacilities::class)
            ->assertSeeText('Clínica Bíblica')
            ->searchTable('clinica biblica')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$draft]);
    });

    it('activa en lote e informa de los que no cumplen los requisitos', function () {
        $ready = makeFacility(['name' => 'Lista']);
        app(FacilityLocationsAction::class)->create($ready, ['city_id' => city()->id, 'address' => 'x'], null);
        app(FacilityContactsAction::class)->save($ready, null, ['type' => 'phone', 'value' => '2222-7777'], null);
        $incomplete = makeFacility(['name' => 'Incompleta']);

        Livewire::test(ListFacilities::class)
            ->callTableBulkAction('publishSelected', [$ready, $incomplete])
            ->assertNotified('1 activados, 1 sin activar');

        expect($ready->fresh()->status->value)->toBe('active')
            ->and($incomplete->fresh()->status->value)->toBe('draft');
    });

    it('las ubicaciones muestran su establecimiento y se filtran por él', function () {
        $facility = activeFacility(['name' => 'Hospital Central']);
        $sede = $facility->locations()->first();
        $loose = app(SaveLocationAction::class)->create(['city_id' => city()->id, 'address' => 'Suelta 1'], null);

        Livewire::test(ListLocations::class)
            ->assertSeeText('Hospital Central')
            ->filterTable('facility_id', $facility->id)
            ->assertCanSeeTableRecords([$sede])
            ->assertCanNotSeeTableRecords([$loose]);
    });
});

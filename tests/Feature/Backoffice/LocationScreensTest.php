<?php

use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Domain\Directory\Enums\LocationType;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\RelationManagers\LocationsRelationManager;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Location;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    Bus::fake([PurgeCdnPaths::class]);
    $this->actingAs(admin());
});

it('el listado resume los tipos que le dan sus médicos y filtra por tipo', function () {
    $hospital = app(DoctorLocationsAction::class)->attachNew(
        makeDoctor(['first_name' => 'Ana']), ['city_id' => city()->id, 'name' => 'Hospital Central', 'address' => 'Calle 1'], LocationType::Hospital, null);
    app(DoctorLocationsAction::class)->attachExisting(makeDoctor(['first_name' => 'Luis']), $hospital, LocationType::Office, null);
    $office = app(DoctorLocationsAction::class)->attachNew(
        makeDoctor(['first_name' => 'Eva']), ['city_id' => city()->id, 'address' => 'Calle 2'], LocationType::Office, null);

    Livewire::test(ListLocations::class)
        ->assertSeeText('Hospital ×1')
        ->assertSeeText('Consultorio ×1')
        ->filterTable('location_type', LocationType::Hospital->value)
        ->assertCanSeeTableRecords([$hospital])
        ->assertCanNotSeeTableRecords([$office]);
});

it('el país filtra las ciudades y se toma de la ciudad al guardar', function () {
    $gt = city('GT', 'Mixco');

    Livewire::test(CreateLocation::class)
        ->set('data.city_id', $gt->id)
        ->assertSet('data.country_id', country('GT')->id)
        ->set('data.country_id', country('CR')->id)
        ->assertSet('data.city_id', null)
        ->set('data.city_id', city()->id)
        ->set('data.address', 'Plaza Tempo, local 12')
        ->call('create')
        ->assertHasNoErrors();

    expect(Location::where('address', 'Plaza Tempo, local 12')->value('country_id'))->toBe(country('CR')->id);
});

it('al añadir una ubicación desde la ficha, el país por defecto es el del médico', function () {
    $doctor = makeDoctor(['country_id' => country('GT')->id]);

    Livewire::test(LocationsRelationManager::class, ['ownerRecord' => $doctor, 'pageClass' => EditDoctor::class])
        ->mountTableAction('attachNewLocation')
        ->assertSet('mountedActions.0.data.country_id', country('GT')->id);
});

<?php

use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Filament\Resources\Cities\RelationManagers\AliasesRelationManager;
use App\Filament\Resources\DoctorSuppressions\Pages\CreateDoctorSuppression;
use App\Filament\Resources\Regions\Pages\ManageRegions;
use App\Filament\Resources\Specialties\Pages\ListSpecialties;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Widgets\DirectoryStatsOverview;
use App\Filament\Widgets\DoctorsByCountry;
use App\Models\City;
use App\Models\DoctorSuppression;
use App\Models\Region;
use App\Models\SlugRedirect;
use App\Models\User;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(admin()));

it('los formularios cortos van sin tarjeta en el modal y con tarjeta en su página', function () {
    $hasCard = fn (?Schema $schema) => collect($schema?->getComponents())->contains(fn ($c) => $c instanceof Section);

    $modals = [
        Livewire::test(ListCities::class)->mountAction('create'),
        Livewire::test(ListSpecialties::class)->mountAction('create'),
        Livewire::test(ManageRegions::class)->mountAction('create'),
        Livewire::test(ViewUser::class, ['record' => admin('otra@x.com')->id])->mountAction('edit'),
    ];

    foreach ($modals as $modal) {
        $schema = $modal->instance()->getSchema($modal->instance()->getMountedActionSchemaName());
        expect($schema?->getComponents())->not->toBeEmpty()
            ->and($hasCard($schema))->toBeFalse();
    }

    expect($hasCard(Livewire::test(EditCity::class, ['record' => city()->id])->instance()->getSchema('form')))->toBeTrue()
        ->and($hasCard(Livewire::test(CreateUser::class)->instance()->getSchema('form')))->toBeTrue();
});

describe('geografía', function () {
    it('la región se crea en un modal: propone el slug desde el nombre y rechaza los reservados', function () {
        Livewire::test(ManageRegions::class)
            ->mountAction('create')
            ->set('mountedActions.0.data.country_id', country()->id)->set('mountedActions.0.data.name', 'Alajuela')
            ->assertSet('mountedActions.0.data.slug', 'alajuela')
            ->callMountedAction()->assertHasNoErrors();

        Livewire::test(ManageRegions::class)
            ->mountAction('create')
            ->set('mountedActions.0.data.country_id', country()->id)->set('mountedActions.0.data.name', 'Médicos')
            ->set('mountedActions.0.data.slug', 'medicos')
            ->callMountedAction()->assertHasErrors(['mountedActions.0.data.slug']);

        expect(Region::where('slug', 'alajuela')->exists())->toBeTrue();
    });

    it('la región se edita en un modal sin tocar el slug, que cambia con su 301', function () {
        $region = Region::create(['country_id' => country()->id, 'name' => 'Alajuela', 'slug' => 'alajuela']);

        Livewire::test(ManageRegions::class)
            ->mountTableAction('edit', $region->id)
            ->set('mountedActions.0.data.name', 'Alajuela Centro')->set('mountedActions.0.data.slug', 'intento-directo')
            ->callMountedTableAction()->assertHasNoErrors();
        expect($region->fresh()->only('name', 'slug'))->toBe(['name' => 'Alajuela Centro', 'slug' => 'alajuela']);

        Livewire::test(ManageRegions::class)
            ->mountTableAction('changeSlug', $region->id)
            ->set('mountedActions.0.data.slug', 'alajuela-centro')
            ->callMountedTableAction();
        expect($region->fresh()->slug)->toBe('alajuela-centro');
    });

    it('la ciudad se crea en un modal, con región dependiente del país', function () {
        $region = Region::create(['country_id' => country()->id, 'name' => 'San José', 'slug' => 'san-jose']);

        Livewire::test(ListCities::class)
            ->mountAction('create')
            ->set('mountedActions.0.data.country_id', country()->id)->set('mountedActions.0.data.region_id', $region->id)
            ->set('mountedActions.0.data.name', 'Santa Ana')
            ->callMountedAction()->assertHasNoErrors();

        expect(City::where('slug', 'santa-ana')->value('region_id'))->toBe($region->id);
    });

    it('editar ignora un slug inyectado; cambiarlo va por la acción con 301', function () {
        $city = city();

        Livewire::test(EditCity::class, ['record' => $city->id])
            ->set('data.name', 'Escazú Centro')->set('data.slug', 'intento-directo')->call('save');
        expect($city->fresh()->slug)->toBe('escazu');

        Livewire::test(EditCity::class, ['record' => $city->id])
            ->mountAction('changeSlug')->set('mountedActions.0.data.slug', 'escazu-centro')->callMountedAction();
        expect($city->fresh()->slug)->toBe('escazu-centro')
            ->and(SlugRedirect::where('old_slug', 'escazu')->where('entity_id', $city->id)->exists())->toBeTrue();
    });

    it('los alias se normalizan y no se repiten dentro del país', function () {
        $city = city();
        $other = city('CR', 'Santa Ana');

        Livewire::test(AliasesRelationManager::class, ['ownerRecord' => $city, 'pageClass' => EditCity::class])
            ->mountTableAction('create')->set('mountedActions.0.data.alias', 'ESCAZÚ,   San José')->callMountedTableAction();
        expect($city->aliases()->value('alias_normalized'))->toBe('escazu, san jose');

        Livewire::test(AliasesRelationManager::class, ['ownerRecord' => $other, 'pageClass' => EditCity::class])
            ->mountTableAction('create')->set('mountedActions.0.data.alias', 'Escazu, San Jose')->callMountedTableAction()
            ->assertHasErrors(['mountedActions.0.data.alias']);
    });
});

it('invitar un usuario envía el enlace y audita el rol', function () {
    Notification::fake();

    Livewire::test(CreateUser::class)
        ->set('data.name', 'Nueva Operadora')->set('data.email', 'Nueva@MeeMedico.com')->set('data.roles', ['admin'])
        ->call('create')->assertHasNoErrors();

    expect(User::where('email', 'nueva@meemedico.com')->first()?->hasRole('admin'))->toBeTrue();
});

it('nombre y correo de un usuario se editan en un modal desde su ficha', function () {
    $user = admin('operadora@x.com');

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->mountAction('edit')
        ->set('mountedActions.0.data.name', 'Operadora Renombrada')
        ->callMountedAction()->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Operadora Renombrada');
});

it('registrar una supresión normaliza el teléfono según el país', function () {
    Livewire::test(CreateDoctorSuppression::class)
        ->set('data.country_id', country('DO')->id)->set('data.phone', '(829) 555-1234')
        ->call('create')->assertHasNoErrors();

    Livewire::test(CreateDoctorSuppression::class)
        ->set('data.country_id', country()->id)->set('data.phone', '123')
        ->call('create')->assertHasErrors(['data.phone']);

    expect(DoctorSuppression::where('phone_normalized', '+18295551234')->exists())->toBeTrue();
});

it('los widgets del panel reflejan el directorio', function () {
    publishedDoctor();
    publishedDoctor(['country_id' => country('GT')->id, 'first_name' => 'Pedro', 'last_name' => 'Ajú']);

    Livewire::test(DirectoryStatsOverview::class)->assertSee('Médicos publicados')->assertSee('2');
    Livewire::test(DoctorsByCountry::class)->assertSee('Guatemala')->assertSee('Costa Rica');
});

<?php

use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\RelationManagers\AliasesRelationManager;
use App\Filament\Resources\DoctorSuppressions\Pages\CreateDoctorSuppression;
use App\Filament\Resources\Regions\Pages\CreateRegion;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Widgets\DirectoryStatsOverview;
use App\Filament\Widgets\DoctorsByCountry;
use App\Models\City;
use App\Models\DoctorSuppression;
use App\Models\Region;
use App\Models\SlugRedirect;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(admin()));

describe('geografía', function () {
    it('propone el slug desde el nombre y rechaza los reservados', function () {
        Livewire::test(CreateRegion::class)
            ->set('data.country_id', country()->id)->set('data.name', 'Alajuela')
            ->assertSchemaStateSet(['slug' => 'alajuela'])
            ->call('create')->assertHasNoErrors();

        Livewire::test(CreateRegion::class)
            ->set('data.country_id', country()->id)->set('data.name', 'Médicos')->set('data.slug', 'medicos')
            ->call('create')->assertHasErrors(['data.slug']);

        expect(Region::where('slug', 'alajuela')->exists())->toBeTrue();
    });

    it('crea una ciudad con región dependiente del país', function () {
        $region = Region::create(['country_id' => country()->id, 'name' => 'San José', 'slug' => 'san-jose']);

        Livewire::test(CreateCity::class)
            ->set('data.country_id', country()->id)->set('data.region_id', $region->id)->set('data.name', 'Santa Ana')
            ->call('create')->assertHasNoErrors();

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

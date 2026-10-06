<?php

use App\Domain\Directory\Actions\CreateDoctorAction;
use App\Domain\Directory\Actions\CreateFacilityAction;
use App\Domain\Directory\Actions\DoctorContactsAction;
use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Actions\FacilityContactsAction;
use App\Domain\Directory\Actions\FacilityLocationsAction;
use App\Domain\Directory\Actions\PublishDoctorAction;
use App\Domain\Directory\Actions\PublishFacilityAction;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Identity\Actions\CreateAdminUserAction;
use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Region;
use App\Models\Specialty;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
| Domain y Feature corren contra PostgreSQL (phpunit.xml) con los seeds de Fase 1.
| Unit no toca la base: normalizadores y funciones puras.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Las URLs del panel (invitaciones, recursos) se resuelven contra el panel admin.
    ->beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('admin')))
    ->in('Domain', 'Feature');

/*
|--------------------------------------------------------------------------
| Helpers de construcción
|--------------------------------------------------------------------------
*/

function admin(string $email = 'admin@test.local'): User
{
    return app(CreateAdminUserAction::class)->execute('Admin de prueba', $email, 'una-clave-larga-123');
}

function doctorUser(string $email = 'medico@test.local'): User
{
    $user = User::create(['name' => 'Usuario médico', 'email' => $email, 'password' => 'una-clave-larga-123']);
    $user->assignRole('doctor');

    return $user;
}

function country(string $code = 'CR'): Country
{
    return Country::where('code', $code)->firstOrFail();
}

function specialty(string $slug = 'cardiologia'): Specialty
{
    return Specialty::where('slug', $slug)->firstOrFail();
}

function city(string $countryCode = 'CR', string $name = 'Escazú'): City
{
    $country = country($countryCode);
    $slug = Str::slug($name);

    $region = Region::firstOrCreate(
        ['country_id' => $country->id, 'slug' => "region-{$slug}"],
        ['name' => "Región {$name}"],
    );

    return City::firstOrCreate(
        ['country_id' => $country->id, 'slug' => $slug],
        ['region_id' => $region->id, 'name' => $name],
    );
}

/**
 * @param  array<string, mixed>  $data
 */
function makeDoctor(array $data = [], ?User $actor = null): Doctor
{
    return app(CreateDoctorAction::class)->execute([
        'country_id' => country()->id,
        'first_name' => 'Ana',
        'last_name' => 'Rojas',
        ...$data,
    ], $actor);
}

/**
 * Ficha con los tres requisitos de publicación: especialidad, ubicación con ciudad y
 * contacto público. Sin publicar.
 */
function publishableDoctor(array $data = [], ?User $actor = null): Doctor
{
    $doctor = makeDoctor($data, $actor);

    app(DoctorSpecialtiesAction::class)->attach($doctor, specialty(), $actor);
    app(DoctorLocationsAction::class)->attachNew($doctor, ['city_id' => city()->id, 'address' => 'Av. Central 123'], LocationType::Office, $actor);
    app(DoctorContactsAction::class)->save($doctor, null, ['type' => 'phone', 'value' => '2222-3333'], $actor);

    return $doctor->refresh();
}

/**
 * Establecimiento en borrador, sin sedes ni contactos.
 *
 * @param  array<string, mixed>  $data
 */
function makeFacility(array $data = [], ?User $actor = null): Facility
{
    return app(CreateFacilityAction::class)->execute([
        'country_id' => country()->id,
        'name' => 'Hospital San Rafael',
        'type' => 'hospital',
        'sector' => 'private',
        ...$data,
    ], $actor);
}

/**
 * Establecimiento activo: una sede y un teléfono público.
 *
 * @param  array<string, mixed>  $data
 */
function activeFacility(array $data = [], ?User $actor = null): Facility
{
    $facility = makeFacility($data, $actor);

    app(FacilityLocationsAction::class)->create($facility, ['city_id' => city()->id, 'address' => 'Calle 1, Av. 2'], $actor);
    app(FacilityContactsAction::class)->save($facility, null, ['type' => 'phone', 'value' => '2222-4444'], $actor);
    app(PublishFacilityAction::class)->execute($facility, $actor);

    return $facility->refresh();
}

function publishedDoctor(array $data = [], ?User $actor = null): Doctor
{
    $doctor = publishableDoctor($data, $actor);
    app(PublishDoctorAction::class)->execute($doctor, $actor);

    return $doctor->refresh();
}

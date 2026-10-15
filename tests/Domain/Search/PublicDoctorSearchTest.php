<?php

use App\Domain\Directory\Actions\DoctorLanguagesAction;
use App\Domain\Directory\Actions\DoctorLocationsAction;
use App\Domain\Directory\Actions\DoctorSpecialtiesAction;
use App\Domain\Directory\Enums\LocationType;
use App\Domain\Search\DoctorSearchService;
use App\Domain\Search\SearchCriteria;
use App\Models\Language;
use App\Models\Specialty;

/*
| Búsqueda pública (DoctorSearchService): solo fichas publicadas del país, en la ciudad
| de su ubicación PRINCIPAL, con las subespecialidades dentro de su padre.
*/

function findIds(SearchCriteria $criteria): array
{
    return collect(app(DoctorSearchService::class)->search($criteria)->items())->pluck('id')->all();
}

it('devuelve solo fichas publicadas del país', function () {
    $published = publishedDoctorIn('Escazú', data: ['first_name' => 'Ana', 'last_name' => 'Rojas']);
    publishableDoctor(['first_name' => 'Borrador', 'last_name' => 'Sin Publicar']);
    publishedDoctorIn('Mixco', data: ['first_name' => 'Luis', 'last_name' => 'Méndez'], countryCode: 'GT');

    expect(findIds(new SearchCriteria(country())))->toBe([$published->id]);
});

it('ubica al médico solo en la ciudad de su ubicación principal', function () {
    $doctor = publishedDoctorIn('Escazú');
    app(DoctorLocationsAction::class)->attachNew($doctor, ['city_id' => city('CR', 'San José')->id, 'address' => 'Av. 2'], LocationType::Office, null);

    $search = fn (string $city) => findIds(new SearchCriteria(country(), specialty(), city: city('CR', $city)));

    expect($search('Escazú'))->toBe([$doctor->id])
        ->and($search('San José'))->toBe([]);
});

it('incluye las subespecialidades en el padre y las filtra con sub', function () {
    $child = Specialty::create(['parent_id' => specialty('cardiologia')->id, 'name' => 'Cardiología Intervencionista', 'slug' => 'cardiologia-intervencionista']);
    $general = publishedDoctorIn('Escazú', data: ['first_name' => 'Ana', 'last_name' => 'General']);
    $interventional = publishedDoctorIn('Escazú', data: ['first_name' => 'Eva', 'last_name' => 'Intervencionista']);
    app(DoctorSpecialtiesAction::class)->attach($interventional, $child, null);

    expect(findIds(new SearchCriteria(country(), specialty(), city: city())))->toEqualCanonicalizing([$general->id, $interventional->id])
        ->and(findIds(new SearchCriteria(country(), specialty(), $child, city())))->toBe([$interventional->id]);
});

it('filtra por idioma', function () {
    $english = Language::where('code', 'en')->firstOrFail();
    $bilingual = publishedDoctorIn('Escazú', data: ['first_name' => 'Ana', 'last_name' => 'Bilingüe']);
    publishedDoctorIn('Escazú', data: ['first_name' => 'Eva', 'last_name' => 'Monolingüe']);
    app(DoctorLanguagesAction::class)->attach($bilingual, $english, null);

    expect(findIds(new SearchCriteria(country(), language: $english)))->toBe([$bilingual->id]);
});

it('busca por nombre sin acentos y por fragmentos', function () {
    $perez = publishedDoctorIn('Escazú', data: ['first_name' => 'José', 'last_name' => 'Pérez Soto']);
    publishedDoctorIn('Escazú', data: ['first_name' => 'Ana', 'last_name' => 'Rojas']);

    expect(findIds(new SearchCriteria(country(), text: 'perez')))->toBe([$perez->id])
        ->and(findIds(new SearchCriteria(country(), text: 'Pér')))->toBe([$perez->id]);
});

it('ordena primero a los verificados y luego por apellido', function () {
    $zuniga = publishedDoctorIn('Escazú', data: ['first_name' => 'Ana', 'last_name' => 'Zúñiga']);
    $alvarez = publishedDoctorIn('Escazú', data: ['first_name' => 'Eva', 'last_name' => 'Álvarez']);
    $zuniga->forceFill(['verification_status' => 'verified', 'verified_at' => now(), 'verification_source' => 'manual'])->save();

    expect(findIds(new SearchCriteria(country())))->toBe([$zuniga->id, $alvarez->id]);
});

it('cuenta médicos por ciudad y por especialidad, con las hijas en el padre', function () {
    $child = Specialty::create(['parent_id' => specialty('cardiologia')->id, 'name' => 'Cardiología Intervencionista', 'slug' => 'cardiologia-intervencionista']);
    publishedDoctorIn('Escazú', data: ['first_name' => 'Ana', 'last_name' => 'Uno']);
    publishedDoctorIn('Escazú', data: ['first_name' => 'Eva', 'last_name' => 'Dos']);
    $third = publishedDoctorIn('San José', 'pediatria', ['first_name' => 'Luz', 'last_name' => 'Tres']);
    app(DoctorSpecialtiesAction::class)->attach($third, $child, null);

    $search = app(DoctorSearchService::class);
    $cities = $search->cityFacets(new SearchCriteria(country()))->mapWithKeys(fn ($i) => [$i['city']->name => $i['total']]);
    $specialties = $search->specialtyFacets(country())->mapWithKeys(fn ($i) => [$i['specialty']->slug => $i['total']]);

    expect($cities->all())->toBe(['Escazú' => 2, 'San José' => 1])
        ->and($specialties->all())->toBe(['cardiologia' => 3, 'pediatria' => 1]);
});

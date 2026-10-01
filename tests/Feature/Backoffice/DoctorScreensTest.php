<?php

use App\Domain\Directory\Actions\CreateSuppressionAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Doctors\Pages\CreateDoctor;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\LocationsRelationManager;
use App\Filament\Resources\Doctors\RelationManagers\SpecialtiesRelationManager;
use App\Filament\Resources\DoctorSuppressions\Pages\ListDoctorSuppressions;
use App\Filament\Resources\DoctorSuppressions\Pages\ViewDoctorSuppression;
use App\Models\Doctor;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

/*
| Los formularios usan set() y mountAction(): reproducen la interacción del navegador
| (fillForm no dispara afterStateUpdated en Filament 5).
*/

beforeEach(function () {
    Bus::fake([PurgeCdnPaths::class]);
    $this->actingAs($this->admin = admin());
});

function relation(string $class, Doctor $doctor)
{
    return Livewire::test($class, ['ownerRecord' => $doctor->fresh(), 'pageClass' => EditDoctor::class]);
}

it('el operador completa y publica una ficha de principio a fin', function () {
    $city = city();

    Livewire::test(CreateDoctor::class)
        ->set('data.country_id', country()->id)
        ->set('data.first_name', 'Ana')
        ->set('data.last_name', 'Rojas Méndez')
        ->set('data.license_number', 'MED-4821')
        ->call('create')
        ->assertHasNoErrors();

    $doctor = Doctor::where('license_number', 'MED-4821')->firstOrFail();
    expect($doctor->slug)->toBe('ana-rojas-mendez');

    relation(SpecialtiesRelationManager::class, $doctor)->mountTableAction('attachSpecialty')
        ->set('mountedActions.0.data.specialty_id', specialty()->id)->callMountedTableAction();

    relation(LocationsRelationManager::class, $doctor)->mountTableAction('attachNewLocation')
        ->set('mountedActions.0.data.city_id', $city->id)
        ->set('mountedActions.0.data.address', 'Plaza Tempo, local 12')
        ->set('mountedActions.0.data.location_type', 'office')
        ->callMountedTableAction();

    relation(ContactsRelationManager::class, $doctor)->mountTableAction('addContact')
        ->set('mountedActions.0.data.type', 'phone')->set('mountedActions.0.data.value', '2222-3333')
        ->set('mountedActions.0.data.is_public', true)->callMountedTableAction();

    $page = Livewire::test(EditDoctor::class, ['record' => $doctor->id]);
    expect(substr_count($page->html(), 'text-success-600'))->toBe(3);

    $page->mountAction('publish')->callMountedAction();

    expect($doctor->fresh()->status->value)->toBe('active');
    Bus::assertDispatched(PurgeCdnPaths::class, fn ($job) => in_array('/costa-rica/medicos/ana-rojas-mendez', $job->paths)
        && in_array('/costa-rica/cardiologia/escazu', $job->paths));
});

it('el formulario muestra el error de licencia repetida en su campo', function () {
    makeDoctor(['license_number' => 'MED-1']);

    Livewire::test(CreateDoctor::class)
        ->set('data.country_id', country()->id)->set('data.first_name', 'X')->set('data.last_name', 'Y')
        ->set('data.license_number', 'MED-1')->call('create')
        ->assertHasErrors(['data.license_number']);
});

it('pide confirmar el homónimo de una supresión antes de crear', function () {
    app(CreateSuppressionAction::class)->execute(country(), null, null, null, 'Pedro Soto', 'x', now(), null);

    $form = Livewire::test(CreateDoctor::class)
        ->set('data.country_id', country()->id)->set('data.first_name', 'Pedro')->set('data.last_name', 'Soto');
    expect($form->html())->toContain('Confirmo que es otra persona');

    $form->call('create');
    expect(Doctor::where('last_name', 'Soto')->exists())->toBeFalse();

    $form->set('data.confirm_homonym', true)->call('create');
    expect(Doctor::where('last_name', 'Soto')->exists())->toBeTrue();
});

it('desde la interfaz no se puede dejar incompleta una ficha publicada', function () {
    $doctor = publishedDoctor();

    relation(ContactsRelationManager::class, $doctor)->mountTableAction('removeContact', $doctor->contacts()->first()->id)->callMountedTableAction();
    relation(SpecialtiesRelationManager::class, $doctor)->mountTableAction('detachSpecialty', specialty()->id)->callMountedTableAction();

    expect($doctor->contacts()->count())->toBe(1)
        ->and($doctor->specialties()->count())->toBe(1);
});

it('guarda datos y perfil sin tocar el slug; verificar y cambiar el slug van por acciones', function () {
    $doctor = publishedDoctor(['license_number' => 'MED-9']);

    Livewire::test(EditDoctor::class, ['record' => $doctor->id])
        ->set('data.first_name', 'Ana María')->set('data.headline', 'Cardióloga clínica')->call('save');
    expect($doctor->fresh()->slug)->toBe('ana-rojas')
        ->and($doctor->profile->fresh()->headline)->toBe('Cardióloga clínica');

    Livewire::test(EditDoctor::class, ['record' => $doctor->id])
        ->mountAction('verify')->set('mountedActions.0.data.source', 'official_registry')->callMountedAction();
    expect($doctor->fresh()->verified_by_user_id)->toBe($this->admin->id);

    Livewire::test(EditDoctor::class, ['record' => $doctor->id])
        ->mountAction('changeSlug')->set('mountedActions.0.data.slug', 'ana-maria-rojas')->callMountedAction();
    expect($doctor->fresh()->slug)->toBe('ana-maria-rojas');
});

it('no ofrece acción de borrado de fichas', function () {
    $page = Livewire::test(EditDoctor::class, ['record' => makeDoctor()->id])->instance();

    expect($page->getAction('delete'))->toBeNull()
        ->and($page->getAction('publish'))->not->toBeNull();
});

it('desactivar la ciudad de una ficha publicada se rechaza desde la interfaz', function () {
    $doctor = publishedDoctor();
    $city = $doctor->locations()->first()->city;

    Livewire::test(EditCity::class, ['record' => $city->id])->mountAction('toggleStatus')->callMountedAction();

    expect($city->fresh()->status->value)->toBe('active');
});

it('una supresión permite despublicar las fichas que coinciden', function () {
    $doctor = publishedDoctor(['license_number' => 'MED-4821']);
    $suppression = app(CreateSuppressionAction::class)->execute(country(), 'MED-4821', null, null, null, 'Pidió salir', now(), null);

    Livewire::test(ViewDoctorSuppression::class, ['record' => $suppression->id])->mountAction('unpublishMatches')->callMountedAction();

    expect($doctor->fresh()->status->value)->toBe('inactive');
});

it('una supresión se revoca desde su vista y deja de ofrecer despublicar', function () {
    publishedDoctor(['license_number' => 'MED-4821']);
    $suppression = app(CreateSuppressionAction::class)->execute(country(), 'MED-4821', null, null, null, 'Pidió salir', now(), null);

    Livewire::test(ViewDoctorSuppression::class, ['record' => $suppression->id])
        ->assertActionVisible('unpublishMatches')
        ->mountAction('revoke')
        ->set('mountedActions.0.data.reason', 'Correo; confirmó por llamada')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($suppression->fresh()->isRevoked())->toBeTrue();

    Livewire::test(ViewDoctorSuppression::class, ['record' => $suppression->id])
        ->assertActionHidden('unpublishMatches')
        ->assertActionHidden('revoke')
        ->assertSeeText('Revocada');
});

it('el listado de supresiones explica para qué sirven, encima de la tabla', function () {
    $suppression = app(CreateSuppressionAction::class)->execute(country(), 'MED-4821', null, null, null, 'Pidió salir', now(), null);

    Livewire::test(ListDoctorSuppressions::class)
        ->assertSeeText('¿Qué es una supresión?')
        ->assertSeeText('Revocar supresión')
        ->assertCanSeeTableRecords([$suppression]);
});

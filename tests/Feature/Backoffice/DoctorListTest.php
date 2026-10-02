<?php

use App\Domain\Directory\Actions\UpdateDoctorAction;
use App\Domain\Directory\Jobs\PublishDoctorsJob;
use App\Filament\Resources\Doctors\Actions\DoctorBulkActions;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\Doctors\RelationManagers\ActivitiesRelationManager;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| La lista de médicos para trabajar con volumen: búsqueda sin acentos, colas en
| pestañas, publicación en lote y el historial de cada ficha.
*/

beforeEach(fn () => $this->actingAs($this->admin = admin()));

it('la búsqueda ignora acentos y el orden de las palabras', function () {
    $jose = makeDoctor(['first_name' => 'José', 'last_name' => 'Pérez']);
    $ana = makeDoctor(['first_name' => 'Ana', 'last_name' => 'Núñez']);

    Livewire::test(ListDoctors::class)
        ->searchTable('perez jose')
        ->assertCanSeeTableRecords([$jose])
        ->assertCanNotSeeTableRecords([$ana]);
});

it('las pestañas separan lo listo para publicar de lo incompleto', function () {
    $ready = publishableDoctor(['first_name' => 'Lista']);
    $incomplete = makeDoctor(['first_name' => 'Incompleta']);

    Livewire::test(ListDoctors::class)
        ->set('activeTab', 'ready')
        ->assertCanSeeTableRecords([$ready])->assertCanNotSeeTableRecords([$incomplete])
        ->set('activeTab', 'incomplete')
        ->assertCanSeeTableRecords([$incomplete])->assertCanNotSeeTableRecords([$ready]);
});

it('publicar en lote publica las que cumplen y deja las demás en borrador', function () {
    $ready = publishableDoctor(['first_name' => 'Lista']);
    $incomplete = makeDoctor(['first_name' => 'Incompleta']);

    Livewire::test(ListDoctors::class)
        ->callTableBulkAction('publishSelected', [$ready, $incomplete])
        ->assertNotified('Publicación en lote');

    expect($ready->fresh()->status->value)->toBe('active')
        ->and($incomplete->fresh()->status->value)->toBe('draft');
});

it('una selección grande se publica en segundo plano', function () {
    $doctors = collect(range(1, DoctorBulkActions::SYNC_LIMIT + 1))
        ->map(fn (int $i) => makeDoctor(['first_name' => "Médico{$i}"]));
    Queue::fake();

    Livewire::test(ListDoctors::class)->callTableBulkAction('publishSelected', $doctors);

    Queue::assertPushed(PublishDoctorsJob::class, fn (PublishDoctorsJob $job) => count($job->doctorIds) === $doctors->count());
});

it('despublicar en lote solo afecta a las publicadas', function () {
    $published = publishedDoctor(['first_name' => 'Publicada']);
    $draft = makeDoctor(['first_name' => 'Borrador']);

    Livewire::test(ListDoctors::class)->callTableBulkAction('unpublishSelected', [$published, $draft]);

    expect($published->fresh()->status->value)->toBe('inactive')
        ->and($draft->fresh()->status->value)->toBe('draft');
});

it('la ficha muestra su historial, con el antes y el después de cada campo', function () {
    $doctor = makeDoctor();
    app(UpdateDoctorAction::class)->execute($doctor, ['last_name' => 'Rojas Mora'], $this->admin);
    $activity = Activity::where('subject_id', $doctor->id)->where('event', 'doctor.updated')->firstOrFail();

    Livewire::test(ActivitiesRelationManager::class, ['ownerRecord' => $doctor, 'pageClass' => EditDoctor::class])
        ->assertSeeText('Ficha modificada')
        ->assertSeeText('Campos: Apellidos')
        ->mountTableAction('view', $activity->id)
        ->assertMountedActionModalSee(['Antes', 'Después', 'Rojas Mora']);
});

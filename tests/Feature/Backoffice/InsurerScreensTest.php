<?php

use App\Domain\Directory\Actions\DoctorInsurersAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Domain\Directory\Jobs\ApplyInsurerToDoctorsJob;
use App\Filament\Resources\Doctors\Actions\DoctorBulkActions;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\Doctors\RelationManagers\InsurersRelationManager as DoctorInsurersTab;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Facilities\RelationManagers\InsurersRelationManager as FacilityInsurersTab;
use App\Filament\Resources\Insurers\Pages\EditInsurer;
use App\Filament\Resources\Insurers\Pages\ListInsurers;
use App\Filament\Resources\Insurers\RelationManagers\DoctorsRelationManager;
use App\Models\Insurer;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

/*
| Pantallas de seguros (MODULO-ESTABLECIMIENTOS-SEGUROS.md §7.2). Las reglas se prueban en
| tests/Domain/Directory/InsurerTest.php; aquí, que cada pantalla llega a ellas.
*/

beforeEach(function () {
    Bus::fake([PurgeCdnPaths::class]);
    $this->actingAs(admin());
});

it('la aseguradora se crea en un modal con su slug, único por país', function () {
    Livewire::test(ListInsurers::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.country_id', country()->id)
        ->set('mountedActions.0.data.name', 'Pan-American Life')
        ->assertSet('mountedActions.0.data.slug', 'pan-american-life')
        ->callMountedAction()->assertHasNoErrors();

    Livewire::test(ListInsurers::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.country_id', country()->id)
        ->set('mountedActions.0.data.name', 'Pan-American Life')
        ->callMountedAction()->assertHasErrors(['mountedActions.0.data.slug']);

    Livewire::test(ListInsurers::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.country_id', country('GT')->id)
        ->set('mountedActions.0.data.name', 'Pan-American Life')
        ->callMountedAction()->assertHasNoErrors();

    expect(Insurer::where('slug', 'pan-american-life')->count())->toBe(2);
});

it('el médico añade y quita una aseguradora desde su ficha', function () {
    $doctor = makeDoctor();
    $bmi = insurer();
    $tab = fn () => Livewire::test(DoctorInsurersTab::class, ['ownerRecord' => $doctor->fresh(), 'pageClass' => EditDoctor::class]);

    $tab()->mountTableAction('attachInsurer')
        ->set('mountedActions.0.data.insurer_id', $bmi->id)->callMountedTableAction()->assertHasNoErrors();
    expect($doctor->insurers()->count())->toBe(1);

    $tab()->assertCanSeeTableRecords([$bmi])->callTableAction('detachInsurer', $bmi);
    expect($doctor->insurers()->count())->toBe(0);
});

it('la ficha del médico solo acepta aseguradoras activas de su país', function (Closure $insurer) {
    $doctor = makeDoctor();

    Livewire::test(DoctorInsurersTab::class, ['ownerRecord' => $doctor, 'pageClass' => EditDoctor::class])
        ->mountTableAction('attachInsurer')
        ->set('mountedActions.0.data.insurer_id', $insurer()->id)
        ->callMountedTableAction()
        ->assertHasErrors(['mountedActions.0.data.insurer_id']);

    expect($doctor->insurers()->count())->toBe(0);
})->with([
    'de otro país' => [fn () => insurer('IGSS', 'GT')],
    'inactiva' => [fn () => insurer('Vieja', 'CR', ['status' => 'inactive'])],
]);

it('el establecimiento registra sus convenios', function () {
    $facility = activeFacility();
    $bmi = insurer();

    Livewire::test(FacilityInsurersTab::class, ['ownerRecord' => $facility, 'pageClass' => EditFacility::class])
        ->mountTableAction('attachInsurer')
        ->set('mountedActions.0.data.insurer_id', $bmi->id)
        ->callMountedTableAction()->assertHasNoErrors();

    expect($facility->insurers()->pluck('name')->all())->toBe(['BMI Seguros']);
});

it('la ficha de la aseguradora lista sus médicos y permite añadir uno', function () {
    $bmi = insurer();
    $ana = makeDoctor();
    $luis = makeDoctor(['first_name' => 'Luis']);
    app(DoctorInsurersAction::class)->attach($ana, $bmi, null);

    Livewire::test(DoctorsRelationManager::class, ['ownerRecord' => $bmi, 'pageClass' => EditInsurer::class])
        ->assertCanSeeTableRecords([$ana])
        ->assertCanNotSeeTableRecords([$luis])
        ->mountTableAction('attachDoctor')
        ->set('mountedActions.0.data.doctor_id', $luis->id)
        ->callMountedTableAction()->assertHasNoErrors();

    expect($bmi->doctors()->count())->toBe(2);
});

describe('en lote desde el listado de médicos', function () {
    it('asigna la aseguradora, omite los de otro país e informa', function () {
        $bmi = insurer();
        $a = makeDoctor(['first_name' => 'Ana']);
        $b = makeDoctor(['first_name' => 'Luis']);
        $gt = makeDoctor(['first_name' => 'Eva', 'country_id' => country('GT')->id]);

        Livewire::test(ListDoctors::class)
            ->callTableBulkAction('assignInsurer', [$a, $b, $gt], ['insurer_id' => $bmi->id])
            ->assertNotified($bmi->name);

        expect($bmi->doctors()->pluck('first_name')->sort()->values()->all())->toBe(['Ana', 'Luis']);

        Livewire::test(ListDoctors::class)
            ->filterTable('insurer', $bmi->id)
            ->assertCanSeeTableRecords([$a, $b])
            ->assertCanNotSeeTableRecords([$gt]);
    });

    it('quita la aseguradora a los seleccionados', function () {
        $bmi = insurer();
        $a = makeDoctor();
        app(DoctorInsurersAction::class)->attach($a, $bmi, null);

        Livewire::test(ListDoctors::class)->callTableBulkAction('removeInsurer', [$a], ['insurer_id' => $bmi->id]);

        expect($a->insurers()->count())->toBe(0);
    });

    it('con más de '.DoctorBulkActions::SYNC_LIMIT.' médicos va a la cola', function () {
        Bus::fake([ApplyInsurerToDoctorsJob::class]);
        $bmi = insurer();
        $doctors = collect(range(1, DoctorBulkActions::SYNC_LIMIT + 1))->map(fn ($n) => makeDoctor(['first_name' => "Médico {$n}"]));

        Livewire::test(ListDoctors::class)->callTableBulkAction('assignInsurer', $doctors, ['insurer_id' => $bmi->id]);

        Bus::assertDispatched(ApplyInsurerToDoctorsJob::class, fn ($job) => count($job->doctorIds) === DoctorBulkActions::SYNC_LIMIT + 1 && $job->attach);
        expect($bmi->doctors()->count())->toBe(0);
    });
});

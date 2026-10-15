<?php

use App\Filament\Resources\Cities\Pages\ListCities;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use Filament\Actions\Action;
use Livewire\Livewire;

/*
| Convenciones de presentación del backoffice (design-system.md §12.5): se fijan una
| vez en PanelDefaults y se comprueban aquí para que una tabla nueva no las rompa.
*/

beforeEach(fn () => $this->actingAs(admin()));

it('las acciones de fila son solo icono, con el texto en tooltip y aria-label', function (string $page) {
    $actions = collect(Livewire::test($page)->instance()->getTable()->getRecordActions())
        ->filter(fn ($action) => $action instanceof Action);

    expect($actions)->not->toBeEmpty();
    $actions->each(fn (Action $action) => expect($action->isIconButton())->toBeTrue());
})->with([ListDoctors::class, ListCities::class, ListUsers::class]);

it('la lista renderiza el texto de la acción como aria-label, no como texto visible', function () {
    makeDoctor();

    // El enlace "Restablecer" del panel de filtros sigue siendo un enlace: no es de fila.
    Livewire::test(ListDoctors::class)
        ->assertSeeHtml('fi-ac-icon-btn-action')
        ->assertSeeHtml('aria-label="Editar"');
});

it('las acciones de cabecera conservan el texto: tienen consecuencias', function () {
    $doctor = makeDoctor();

    $component = Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()]);

    expect($component->instance()->getAction('publish')->isIconButton())->toBeFalse();
});

it('los títulos solo llevan mayúscula inicial', function () {
    expect(ImportBatchResource::getTitleCasePluralModelLabel())->toBe('Lotes de importación')
        ->and(ImportBatchResource::getTitleCaseModelLabel())->toBe('Lote de importación');
});

it('los roles se muestran con su nombre legible', function () {
    expect(Role::labelFor('admin'))->toBe('Administrador')
        ->and(Role::labelFor('rol-sin-etiqueta'))->toBe('rol-sin-etiqueta');
});

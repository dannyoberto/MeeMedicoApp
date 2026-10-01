<?php

namespace App\Filament\Resources\DoctorSuppressions\Pages;

use App\Filament\Resources\DoctorSuppressions\DoctorSuppressionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

class ListDoctorSuppressions extends ListRecords
{
    protected static string $resource = DoctorSuppressionResource::class;

    public function getSubheading(): ?string
    {
        return 'Médicos que pidieron no aparecer en MeeMedico.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Explicación para quien llega nuevo al backoffice (DATABASE.md §14.1), encima de la tabla.
     * Plegable, y recuerda el estado en el navegador para no estorbar a quien ya la conoce.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('¿Qué es una supresión?')
                    ->id('suppressions-help')
                    ->description('Léelo antes de registrar o revocar una. Puedes plegar este recuadro.')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->collapsible()
                    ->persistCollapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])->schema([
                            self::block('Qué es', [
                                Text::make('El registro de que un médico pidió no aparecer en el directorio. Cargamos fichas desde fuentes públicas sin su consentimiento: esta es su forma de salir y la prueba de que lo respetamos.'),
                                Text::make('Es un registro legal: no se edita ni se borra.'),
                            ]),
                            self::block('Qué bloquea mientras está vigente', [
                                Text::make('Ninguna ficha con ese colegiado, teléfono o correo puede crearse, importarse ni publicarse en ese país.'),
                                Text::make('Si solo coincide el nombre, el sistema avisa pero no bloquea: puede ser otra persona que se llama igual.'),
                            ]),
                            self::block('Cómo se usa', [
                                UnorderedList::make([
                                    Text::make('Crea la supresión con al menos una clave. El colegiado es la más fiable.'),
                                    Text::make('En su detalle, revisa las fichas que coinciden. No se despublican solas: usa «Despublicar coincidencias».'),
                                    Text::make('Si el médico quiere volver, verifica su identidad y usa «Revocar supresión». Después publica su ficha.'),
                                ]),
                            ]),
                        ]),
                    ]),
                // Lo que ListRecords::content() pone por defecto.
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * @param  array<int, mixed>  $content
     */
    private static function block(string $title, array $content): Group
    {
        return Group::make([
            Text::make($title)->weight(FontWeight::SemiBold),
            ...$content,
        ]);
    }
}

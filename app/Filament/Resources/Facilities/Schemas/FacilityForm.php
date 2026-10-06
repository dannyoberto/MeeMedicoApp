<?php

namespace App\Filament\Resources\Facilities\Schemas;

use App\Domain\Directory\Enums\FacilityNetworkStatus;
use App\Domain\Directory\Enums\FacilitySector;
use App\Domain\Directory\Enums\FacilityType;
use App\Models\Facility;
use App\Models\FacilityNetwork;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

/**
 * Crear: datos del establecimiento (CreateFacilityAction); sedes y contactos se añaden al editar.
 * Editar: Datos (UpdateFacilityAction) y la lista de requisitos para activarlo.
 * El estado y el slug no son campos: cambian con acciones auditadas.
 */
class FacilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(['lg' => 2])
                    ->schema(self::fields()),
                Group::make([
                    self::statusSection(),
                    Section::make('Requisitos para activar')
                        ->schema([
                            View::make('filament.admin.facilities.publication-checklist'),
                        ]),
                ])
                    ->visibleOn('edit')
                    ->columnSpan(['lg' => 1]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function fields(): array
    {
        return [
            Select::make('country_id')
                ->label('País')
                ->relationship('country', 'name')
                ->required()
                ->live()
                // Sus sedes y su red están atadas al país por FK compuesta.
                ->disabled(fn (string $operation) => $operation !== 'create')
                ->helperText(fn (string $operation) => $operation === 'create' ? null : 'Sus sedes y su red son de este país: no se cambia.')
                ->afterStateUpdated(fn (Set $set) => $set('network_id', null)),
            TextInput::make('name')
                ->label('Nombre')
                ->placeholder('Hospital Clínica Bíblica')
                ->required()
                ->maxLength(200),
            Grid::make(2)->schema([
                Select::make('type')->label('Tipo')->options(FacilityType::class)->required(),
                Select::make('sector')->label('Sector')->options(FacilitySector::class)->required(),
            ]),
            Select::make('network_id')
                ->label('Red')
                ->helperText('Institución que lo opera (CCSS, IGSS, un grupo privado…). Opcional.')
                ->options(fn (Get $get) => FacilityNetwork::query()
                    ->where('country_id', $get('country_id'))
                    ->where('status', FacilityNetworkStatus::Active)
                    ->orderBy('name')->pluck('name', 'id'))
                ->disabled(fn (Get $get) => blank($get('country_id')))
                ->live()
                // Propone el sector de la red; se puede cambiar (servicios públicos operados por terceros).
                ->afterStateUpdated(fn (Set $set, ?string $state) => $state
                    && $set('sector', FacilityNetwork::whereKey($state)->value('sector'))),
            Textarea::make('description')
                ->label('Descripción')
                ->helperText('Para su futura página pública: qué es, qué servicios tiene, horario general.')
                ->rows(4),
        ];
    }

    /**
     * Estado de un vistazo. Se cambia con las acciones de la cabecera.
     */
    private static function statusSection(): Section
    {
        return Section::make('Estado')
            ->schema([
                TextEntry::make('status')->label('Estado')->badge()->inlineLabel(),
                TextEntry::make('doctors_total')
                    ->label('Médicos')
                    ->state(fn (Facility $record) => $record->doctors()->count())
                    ->inlineLabel(),
                TextEntry::make('public_path')
                    ->label('Ruta pública (futura)')
                    ->state(fn (Facility $record) => "/{$record->country?->slug}/clinicas/{$record->slug}")
                    ->copyable()
                    ->color('gray')
                    ->size(TextSize::Small),
                TextEntry::make('updated_at')->label('Actualizado')->since()->inlineLabel(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Facilities\Schemas;

use App\Domain\Directory\Enums\FacilityNetworkStatus;
use App\Domain\Directory\Enums\FacilitySector;
use App\Domain\Directory\Enums\FacilityType;
use App\Domain\Geo\Enums\CityStatus;
use App\Domain\Geo\Enums\RegionStatus;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityNetwork;
use App\Models\Region;
use Filament\Forms\Components\FileUpload;
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
 * Crear: datos del establecimiento, su dirección y sus primeros contactos, en una sola
 * operación (CreateFacilityAction). La dirección se guarda como su primera sede y los
 * teléfonos y el correo como contactos: no son columnas del establecimiento.
 * Editar: Datos (UpdateFacilityAction) y la lista de requisitos para activarlo; las sedes
 * y los contactos se gestionan en sus pestañas. El estado y el slug cambian con acciones auditadas.
 */
class FacilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make()->schema(self::fields()),
                    self::addressSection(),
                    self::contactSection(),
                ])->columnSpan(['lg' => 2]),
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
                ->afterStateUpdated(function (Set $set) {
                    $set('network_id', null);
                    $set('region_id', null);
                    $set('city_id', null);
                }),
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
            FileUpload::make('logo_path')
                ->label('Logo')
                ->helperText('PNG, JPG o WebP, hasta 2 MB. Mejor cuadrado y con fondo claro.')
                ->disk(fn () => config('meemedico.media_disk'))
                ->directory('facilities/logos')
                ->visibility('public')
                ->image()
                // Sin SVG: puede llevar scripts y el logo se mostrará en páginas públicas.
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                ->maxSize(2048),
        ];
    }

    /**
     * Primera sede, solo al crear. País → región → ciudad: la FK compuesta de locations
     * exige la cadena coherente, y la región se deriva de la ciudad al guardar.
     */
    private static function addressSection(): Section
    {
        return Section::make('Dirección')
            ->description('Se guarda como su primera sede. Más sedes se añaden después, en la pestaña Sedes.')
            ->visibleOn('create')
            ->columns(2)
            ->schema([
                Select::make('region_id')
                    ->label('Región')
                    ->options(fn (Get $get) => Region::query()
                        ->where('country_id', $get('country_id'))
                        ->where('status', RegionStatus::Active)
                        ->orderBy('name')->pluck('name', 'id'))
                    ->disabled(fn (Get $get) => blank($get('country_id')))
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                    ->required(),
                Select::make('city_id')
                    ->label('Ciudad')
                    ->options(fn (Get $get) => City::query()
                        ->where('region_id', $get('region_id'))
                        ->where('status', CityStatus::Active)
                        ->orderBy('name')->pluck('name', 'id'))
                    ->disabled(fn (Get $get) => blank($get('region_id')))
                    ->searchable()
                    ->required(),
                TextInput::make('address')
                    ->label('Dirección')
                    ->placeholder('Av. 14, entre calles central y 1')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('address_2')
                    ->label('Complemento')
                    ->placeholder('Torre B, piso 3')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->label('Latitud')
                    ->placeholder('9.9325')
                    ->numeric()->minValue(-90)->maxValue(90)
                    ->requiredWith('longitude'),
                TextInput::make('longitude')
                    ->label('Longitud')
                    ->placeholder('-84.0795')
                    ->numeric()->minValue(-180)->maxValue(180)
                    ->requiredWith('latitude')
                    ->helperText('Opcional, para el mapa. En Google Maps: clic derecho sobre el lugar y se copian las coordenadas.'),
            ]);
    }

    /**
     * Primeros contactos, solo al crear. Se guardan como contactos del establecimiento
     * (normalizados a E.164); más se añaden después, en la pestaña Contactos.
     */
    private static function contactSection(): Section
    {
        return Section::make('Contacto')
            ->description('Con un teléfono queda listo para activar. Más contactos se añaden después, en la pestaña Contactos.')
            ->visibleOn('create')
            ->columns(2)
            ->schema([
                TextInput::make('phone_1')->label('Teléfono principal')->tel()->maxLength(255),
                TextInput::make('phone_2')->label('Teléfono secundario')->tel()->maxLength(255),
                TextInput::make('email')->label('Correo')->email()->maxLength(255)->columnSpanFull(),
            ]);
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

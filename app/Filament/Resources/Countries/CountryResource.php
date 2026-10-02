<?php

namespace App\Filament\Resources\Countries;

use App\Domain\Geo\Enums\CountryStatus;
use App\Filament\Actions\ToggleStatusAction;
use App\Filament\Resources\Countries\Pages\ManageCountries;
use App\Models\Country;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Los cuatro países vienen del CountrySeeder: aquí no se crean.
 * code y slug son inmutables (el slug de país viaja en todas las URLs y
 * slug_redirects no admite 'country').
 */
class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAmericas;

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'país';

    protected static ?string $pluralModelLabel = 'países';

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(150),
                TextInput::make('dial_code')
                    ->label('Prefijo telefónico')
                    ->helperText('Con +, sin espacios. Lo usa el importador para normalizar teléfonos a E.164.')
                    ->required()
                    ->regex('/^\+\d{1,4}$/')
                    ->maxLength(5),
                TextInput::make('code')
                    ->label('Código ISO')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('slug')
                    ->label('Slug (URL)')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Código'),
                TextColumn::make('dial_code')
                    ->label('Prefijo'),
                TextColumn::make('slug')
                    ->label('Slug'),
                TextColumn::make('regions_count')
                    ->label('Regiones')
                    ->counts('regions')
                    ->numeric(),
                TextColumn::make('cities_count')
                    ->label('Ciudades')
                    ->counts('cities')
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(CountryStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ToggleStatusAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCountries::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Facilities;

use App\Filament\Resources\Doctors\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Facilities\Pages\CreateFacility;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Facilities\Pages\ListFacilities;
use App\Filament\Resources\Facilities\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\DoctorsRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\InsurersRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\LocationsRelationManager;
use App\Filament\Resources\Facilities\Schemas\FacilityForm;
use App\Filament\Resources\Facilities\Tables\FacilitiesTable;
use App\Models\Facility;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hospitales, clínicas, centros médicos y centros de salud (MODULO-ESTABLECIMIENTOS-SEGUROS.md §7.1).
 * Las reglas viven en las Actions de dominio; aquí solo se presentan.
 */
class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'establecimiento';

    protected static ?string $pluralModelLabel = 'establecimientos';

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FacilityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacilitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LocationsRelationManager::class,
            ContactsRelationManager::class,
            DoctorsRelationManager::class,
            InsurersRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFacilities::route('/'),
            'create' => CreateFacility::route('/create'),
            'edit' => EditFacility::route('/{record}/edit'),
        ];
    }
}

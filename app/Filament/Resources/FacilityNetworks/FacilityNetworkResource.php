<?php

namespace App\Filament\Resources\FacilityNetworks;

use App\Filament\Resources\FacilityNetworks\Pages\ManageFacilityNetworks;
use App\Filament\Resources\FacilityNetworks\Schemas\FacilityNetworkForm;
use App\Filament\Resources\FacilityNetworks\Tables\FacilityNetworksTable;
use App\Models\FacilityNetwork;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Redes operadoras de establecimientos: CCSS, IGSS, SNS, IVSS, grupos privados (DATABASE.md §9.9).
 */
class FacilityNetworkResource extends Resource
{
    protected static ?string $model = FacilityNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Directorio';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'red';

    protected static ?string $pluralModelLabel = 'redes';

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FacilityNetworkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacilityNetworksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFacilityNetworks::route('/'),
        ];
    }
}

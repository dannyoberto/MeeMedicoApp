<?php

namespace App\Filament\Resources\Languages;

use App\Domain\Directory\Enums\LanguageStatus;
use App\Filament\Actions\ToggleStatusAction;
use App\Filament\Resources\Languages\Pages\ManageLanguages;
use App\Models\Language;
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

class LanguageResource extends Resource
{
    protected static ?string $model = Language::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 70;

    protected static ?string $modelLabel = 'idioma';

    protected static ?string $pluralModelLabel = 'idiomas';

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Código ISO 639-1')
                    ->helperText('Dos letras en minúscula: es, en, fr, pt.')
                    ->required()
                    ->regex('/^[a-z]{2}$/')
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation) => $operation !== 'create'),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100),
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
                TextColumn::make('doctors_count')
                    ->label('Médicos')
                    ->counts('doctors')
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(LanguageStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                ToggleStatusAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLanguages::route('/'),
        ];
    }
}

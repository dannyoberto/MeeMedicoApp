<?php

namespace App\Filament\Resources\Specialties\RelationManagers;

use App\Domain\Directory\Enums\SpecialtyAliasKind;
use App\Domain\Geo\Support\Normalize;
use App\Models\SpecialtyAlias;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Nombres alternativos de la especialidad (DATABASE.md §8.2): texto de la fuente de
 * importación, variantes locales por país y sinónimos de búsqueda.
 */
class AliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'aliases';

    protected static ?string $title = 'Alias';

    protected static ?string $modelLabel = 'alias';

    protected static ?string $pluralModelLabel = 'alias';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('alias')
                    ->label('Alias')
                    ->helperText('Ej.: "CARDIOLOGIA CLINICA" (importación), "médico del corazón" (búsqueda).')
                    ->required()
                    ->maxLength(150)
                    // specialty_aliases_alias_uniq: el alias normalizado es único en todo el catálogo.
                    ->rule(fn (?Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                        $exists = SpecialtyAlias::query()
                            ->where('alias_normalized', Normalize::text((string) $value))
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->with('specialty:id,name')
                            ->first();

                        if ($exists) {
                            $fail("Ese alias ya apunta a {$exists->specialty->name}.");
                        }
                    }),
                Select::make('kind')
                    ->label('Tipo')
                    ->options(SpecialtyAliasKind::class)
                    ->default(SpecialtyAliasKind::Import)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alias')
            ->columns([
                TextColumn::make('alias')
                    ->label('Alias')
                    ->searchable(),
                TextColumn::make('alias_normalized')
                    ->label('Clave normalizada')
                    ->color('gray'),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options(SpecialtyAliasKind::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data) => $this->withNormalized($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data) => $this->withNormalized($data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withNormalized(array $data): array
    {
        $data['alias_normalized'] = Normalize::text($data['alias']);

        return $data;
    }
}

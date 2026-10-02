<?php

namespace App\Filament\Resources\Cities\RelationManagers;

use App\Domain\Geo\Support\Normalize;
use App\Models\CityAlias;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Textos libres que el importador debe mapear a esta ciudad (DATABASE.md §7.4).
 * Cada alias grabado aquí hace que el siguiente lote requiera menos revisión manual.
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
                    ->helperText('Tal como aparece en la fuente: "SJ Centro", "San Jose, C.R."')
                    ->required()
                    ->maxLength(150)
                    // city_aliases_country_alias_uniq: el alias normalizado es único por país.
                    ->rule(fn (?Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                        $exists = CityAlias::query()
                            ->where('country_id', $this->getOwnerRecord()->getAttribute('country_id'))
                            ->where('alias_normalized', Normalize::text((string) $value))
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->with('city:id,name')
                            ->first();

                        if ($exists) {
                            $fail("Ese alias ya apunta a {$exists->city->name} en este país.");
                        }
                    }),
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
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data) => $this->withDerivedFields($data, source: 'admin')),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data) => $this->withDerivedFields($data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * La app escribe alias_normalized, nunca el motor (DATABASE.md §3.6), y el país
     * sale siempre de la ciudad: no se elige a mano.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDerivedFields(array $data, ?string $source = null): array
    {
        $data['alias_normalized'] = Normalize::text($data['alias']);
        $data['country_id'] = $this->getOwnerRecord()->getAttribute('country_id');

        if ($source) {
            $data['source'] = $source;
        }

        return $data;
    }
}

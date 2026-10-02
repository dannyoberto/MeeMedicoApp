<?php

namespace App\Filament\Resources\Doctors\Tables;

use App\Domain\Directory\Enums\ClaimStatus;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Domain\Directory\Support\DoctorSearch;
use App\Domain\Geo\Support\Normalize;
use App\Filament\Resources\Doctors\Actions\DoctorBulkActions;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\City;
use App\Models\Doctor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista de médicos pensada para miles de fichas: búsqueda sin acentos (DoctorSearch),
 * colas en pestañas (ListDoctors), filtros que se recuerdan y acciones en lote.
 * Solo la publicación lleva etiqueta de color; el resto, iconos o columnas opcionales.
 */
class DoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('status', '<>', DoctorStatus::Merged)
                ->with([
                    'country:id,name',
                    'specialties' => fn ($q) => $q->wherePivot('is_primary', true),
                    'locations' => fn ($q) => $q->wherePivot('is_primary', true)->with('city:id,name'),
                ]))
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (Doctor $record) => InitialsAvatarProvider::dataUri(DoctorResource::displayName($record)))
                    ->circular()
                    ->imageSize(36),
                TextColumn::make('last_name')
                    ->label('Médico')
                    ->formatStateUsing(fn (Doctor $record) => DoctorResource::displayName($record))
                    ->description(fn (Doctor $record) => $record->license_number ? "Lic. {$record->license_number}" : 'Sin licencia registrada')
                    ->searchable(query: fn (Builder $query, string $search) => DoctorSearch::apply($query, $search))
                    ->sortable(['last_name', 'first_name']),
                IconColumn::make('verification_status')
                    ->label('Verif.')
                    ->tooltip(fn (Doctor $record) => $record->verification_status->getLabel())
                    ->alignCenter(),
                TextColumn::make('specialties.name')
                    ->label('Especialidad')
                    ->placeholder('—'),
                TextColumn::make('primary_city')
                    ->label('Ciudad')
                    ->state(fn (Doctor $record) => $record->locations->first()?->city?->name)
                    ->placeholder('—'),
                TextColumn::make('country.name')
                    ->label('País'),
                TextColumn::make('status')
                    ->label('Publicación')
                    ->badge(),
                TextColumn::make('claim_status')
                    ->label('Reclamación')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            // La búsqueda trata la frase entera: DoctorSearch parte las palabras y exige todas.
            ->splitSearchTerms(false)
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->defaultPaginationPageOption(25)
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('country_id')->label('País')->relationship('country', 'name'),
                SelectFilter::make('status')->label('Publicación')->options(
                    collect(DoctorStatus::cases())->reject(fn ($s) => $s === DoctorStatus::Merged)
                        ->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all(),
                ),
                SelectFilter::make('verification_status')->label('Verificación')->options(VerificationStatus::class),
                SelectFilter::make('claim_status')->label('Reclamación')->options(ClaimStatus::class),
                SelectFilter::make('specialty')
                    ->label('Especialidad')
                    ->relationship('specialties', 'name')
                    ->searchable(),
                self::cityFilter(),
                SelectFilter::make('import_batch_id')
                    ->label('Lote de importación')
                    ->relationship('importBatch', 'file_name')
                    ->searchable(),
                TernaryFilter::make('license')
                    ->label('Colegiado')
                    ->trueLabel('Con colegiado')
                    ->falseLabel('Sin colegiado')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('license_number'),
                        false: fn (Builder $q) => $q->whereNull('license_number'),
                    ),
                TernaryFilter::make('public_contact')
                    ->label('Contacto público')
                    ->trueLabel('Con contacto público')
                    ->falseLabel('Sin contacto público')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('contacts', fn ($c) => $c->where('is_public', true)),
                        false: fn (Builder $q) => $q->whereDoesntHave('contacts', fn ($c) => $c->where('is_public', true)),
                    ),
                Filter::make('created_at')
                    ->label('Alta')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('from')->label('Alta desde'),
                        DatePicker::make('until')->label('Alta hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        filled($data['from'] ?? null) ? 'Alta desde '.$data['from'] : null,
                        filled($data['until'] ?? null) ? 'Alta hasta '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DoctorBulkActions::publish(),
                    DoctorBulkActions::unpublish(),
                ])->label('Acciones en lote'),
            ])
            ->emptyStateHeading(fn ($livewire) => ($livewire->activeTab ?? 'all') === 'all' && blank($livewire->tableSearch ?? null)
                ? 'Aún no hay médicos'
                : 'Ningún médico en esta vista')
            ->emptyStateDescription('Crea una ficha o cárgalas en lote desde Operación → Importación.')
            ->emptyStateActions([
                Action::make('import')
                    ->label('Ir a Importación')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->url(fn () => ImportBatchResource::getUrl('index'))
                    ->visible(fn () => auth()->user()?->can('imports.view') ?? false),
            ]);
    }

    /**
     * Las ciudades se buscan en el servidor: en Venezuela pueden ser miles.
     */
    private static function cityFilter(): Filter
    {
        return Filter::make('city')
            ->schema([
                Select::make('city_id')
                    ->label('Ciudad')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => City::query()
                        ->whereRaw('immutable_unaccent(lower(cities.name)) like ?', ['%'.Normalize::text($search).'%'])
                        ->with('country:id,name')->orderBy('name')->limit(30)->get()
                        ->mapWithKeys(fn (City $c) => [$c->id => "{$c->name} ({$c->country->name})"])->all())
                    ->getOptionLabelUsing(fn ($value) => City::find($value)?->name),
            ])
            ->query(fn (Builder $query, array $data) => $query->when($data['city_id'] ?? null,
                fn (Builder $q, string $city) => $q->whereHas('locations', fn ($l) => $l->where('locations.city_id', $city))))
            ->indicateUsing(fn (array $data) => filled($data['city_id'] ?? null)
                ? 'Ciudad: '.City::find($data['city_id'])?->name
                : null);
    }
}

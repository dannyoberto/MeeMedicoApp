<?php

namespace App\Filament\Resources\ImportBatches\RelationManagers;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Directory\Support\DoctorSearch;
use App\Domain\Directory\Support\SlugRules;
use App\Domain\Geo\Enums\CityStatus;
use App\Domain\Geo\Support\Normalize;
use App\Domain\Import\Actions\MapImportValueAction;
use App\Domain\Import\Actions\ResolveImportRowAction;
use App\Domain\Import\Enums\ImportResolution;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\ImportPipeline;
use App\Domain\Import\Support\RowIssue;
use App\Filament\Support\DomainAction;
use App\Models\City;
use App\Models\Doctor;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Region;
use App\Models\Specialty;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * La cola de revisión del lote. Cada acción es una decisión humana auditada
 * (ResolveImportRowAction) o un mapeo que enseña al importador (MapImportValueAction).
 *
 * "Revisar" abre la fila en un panel lateral con sus incidencias, los candidatos y el
 * dato original, y las decisiones al pie: al decidir, pasa sola a la siguiente fila en
 * revisión. Crear como nuevas y descartar también van en lote; vincular, nunca.
 */
class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    protected static ?string $title = 'Filas';

    private const ISSUE_FILTERS = [
        RowIssue::UNKNOWN_CITY => 'Ciudad no reconocida',
        RowIssue::AMBIGUOUS_CITY => 'Ciudad ambigua',
        RowIssue::UNKNOWN_SPECIALTY => 'Especialidad no reconocida',
        RowIssue::POSSIBLE_DUPLICATE => 'Posible duplicado',
        RowIssue::GROUP_CONFLICT => 'Filas del médico no coinciden',
        RowIssue::DUPLICATE_LICENSE => 'Colegiado repetido en el archivo',
        RowIssue::SUPPRESSION_NAME => 'Homónimo de una supresión',
        RowIssue::MISSING => 'Falta un dato obligatorio',
        RowIssue::EXISTING_VALUE => 'Conflicto con la ficha existente',
        RowIssue::INVALID_PHONE => 'Teléfono o contacto omitido',
    ];

    /** En una vista, Filament deja los gestores en solo lectura; aquí se resuelven filas. */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * La pantalla del lote avisa cuando una etapa empieza o termina: se redibuja la
     * tabla y, con ella, el intervalo de refresco.
     */
    #[On('import-batch-changed')]
    #[On('import-batch-finished')]
    public function refreshRows(): void {}

    private function batch(): ImportBatch
    {
        /** @var ImportBatch */
        return $this->getOwnerRecord();
    }

    private function canResolve(): bool
    {
        return auth()->user()?->can('resolve', $this->batch()) ?? false;
    }

    private static function isPending(ImportRow $row): bool
    {
        return $row->status === ImportRowStatus::NeedsReview;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('row_number')
            ->defaultSort('row_number')
            // Solo mientras corre una etapa: un lote en revisión no cambia solo.
            ->poll(fn () => $this->batch()->refresh()->status->isRunning() ? '10s' : null)
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('row_number')->label('Fila')->sortable(),
                TextColumn::make('doctor_ref')->label('ID del médico')
                    ->state(fn (ImportRow $r) => $r->raw_payload['doctor_ref'] ?? '—'),
                TextColumn::make('name')->label('Médico')
                    ->state(fn (ImportRow $r) => trim(($r->n_first_name ?? $r->raw_payload['first_name'] ?? '').' '.($r->n_last_name ?? $r->raw_payload['last_name'] ?? '')) ?: '—')
                    ->description(fn (ImportRow $r) => $r->raw_payload['license_number'] ?? null),
                TextColumn::make('city')->label('Ciudad (archivo)')
                    ->state(fn (ImportRow $r) => $r->raw_payload['city'] ?? '—')
                    ->description(fn (ImportRow $r) => $r->raw_payload['address'] ?? null),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('match_type')->label('Coincidencia')->badge()
                    ->color(fn (ImportRow $r) => $r->match_confidence?->getColor() ?? 'gray')
                    ->placeholder('—'),
                TextColumn::make('issues')->label('Incidencias')->wrap()
                    ->state(fn (ImportRow $r) => collect($r->validation_errors ?? [])->pluck('message')->implode(' · ') ?: null)
                    ->color(fn (ImportRow $r) => collect($r->validation_errors ?? [])->contains('level', RowIssue::ERROR) ? 'danger' : 'gray')
                    ->placeholder('—'),
                TextColumn::make('appliedDoctor.slug')->label('Ficha')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(ImportRowStatus::class),
                SelectFilter::make('issue')->label('Incidencia')->options(self::ISSUE_FILTERS)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn ($q, $code) => $q->whereRaw('validation_errors @> ?::jsonb', [json_encode([['code' => $code]])]))),
            ])
            ->recordActions([
                $this->reviewAction(),
                ActionGroup::make($this->decisionActions())
                    ->tooltip('Resolver sin abrir la fila')
                    ->visible(fn (ImportRow $r) => self::isPending($r) && $this->canResolve()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    $this->bulkResolveAction(ImportResolution::CreateNew),
                    $this->bulkResolveAction(ImportResolution::Discard),
                ])
                    ->label('Resolver en lote')
                    ->visible(fn () => $this->canResolve()),
            ])
            ->emptyStateHeading('Sin filas');
    }

    /**
     * @param  (Closure(ImportRow): void)|null  $after  qué hacer tras decidir
     * @return array<int, Action>
     */
    private function decisionActions(?Closure $after = null): array
    {
        $actions = [
            $this->assignCityAction(),
            $this->assignSpecialtyAction(),
            $this->createNewAction(),
            $this->linkExistingAction(),
            $this->discardAction(),
        ];

        return $after ? array_map(fn (Action $action) => $action->after($after), $actions) : $actions;
    }

    private function reviewAction(): Action
    {
        return Action::make('details')
            ->label(fn (ImportRow $r) => self::isPending($r) ? 'Revisar' : 'Ver')
            ->icon(Heroicon::OutlinedEye)
            ->color(fn (ImportRow $r) => self::isPending($r) ? 'warning' : 'gray')
            ->slideOver()
            ->modalHeading(fn (ImportRow $r) => "Fila {$r->row_number}")
            ->modalDescription(fn (ImportRow $r) => self::isPending($r) && $this->canResolve()
                ? 'Decide al pie: al hacerlo pasas a la siguiente fila en revisión.'
                : null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (ImportRow $r) => view('filament.admin.imports.row-details', [
                'row' => $r,
                'candidates' => Doctor::whereKey($r->candidate_doctor_ids ?? [])->with('country:id,slug')->get(),
                'pending' => $this->batch()->rows()->where('status', ImportRowStatus::NeedsReview)->count(),
                'running' => $this->batch()->status->isRunning(),
            ]))
            ->extraModalFooterActions(fn (ImportRow $r) => self::isPending($r) && $this->canResolve()
                ? $this->decisionActions(fn (ImportRow $record) => $this->reviewNext($record))
                : []);
    }

    /**
     * Tras decidir desde el panel lateral: abre la siguiente fila en revisión (o la
     * primera, si era la última) y, si no queda ninguna, lo cierra.
     */
    private function reviewNext(ImportRow $current): void
    {
        $pending = fn () => $this->batch()->rows()
            ->where('status', ImportRowStatus::NeedsReview)
            ->whereKeyNot($current->getKey())
            ->orderBy('row_number');

        $next = $pending()->where('row_number', '>', $current->row_number)->first() ?? $pending()->first();

        if ($next) {
            $this->replaceMountedAction('details', context: ['table' => true, 'recordKey' => $next->getKey()]);

            return;
        }

        $this->unmountAction(cancelParentActions: true);
        Notification::make()->success()->title('No quedan filas en revisión')->body('Cuando termine de procesarse, ya se puede aplicar el lote.')->send();
    }

    private function createNewAction(): Action
    {
        return Action::make('createNew')
            ->label('Crear como nueva')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->requiresConfirmation()
            ->modalDescription('Se creará una ficha nueva al aplicar el lote, aunque se parezca a otra (un duplicado es reparable; una fusión equivocada no).')
            ->action(fn (ImportRow $record, Action $action) => $this->resolve($action, $record, ImportResolution::CreateNew));
    }

    private function linkExistingAction(): Action
    {
        return Action::make('linkExisting')
            ->label('Vincular a una ficha existente')
            ->icon(Heroicon::OutlinedLink)
            ->modalDescription('Al aplicar, la ficha elegida se completa con los datos del archivo (sin sobrescribir lo que ya tiene).')
            ->schema([
                Select::make('doctor_id')
                    ->label('Ficha')
                    ->helperText('Primero los candidatos que encontró el importador. Busca por nombre (sin importar acentos) o colegiado.')
                    ->options(fn (ImportRow $record) => $this->doctorOptions(Doctor::whereKey($record->candidate_doctor_ids ?? [])))
                    ->getSearchResultsUsing(fn (string $search) => $this->doctorOptions(DoctorSearch::apply($this->linkableDoctors(), $search)->limit(20)))
                    ->getOptionLabelUsing(fn ($value) => $this->doctorOptions($this->linkableDoctors()->whereKey($value))[$value] ?? null)
                    ->searchable()
                    ->required(),
            ])
            ->action(fn (array $data, ImportRow $record, Action $action) => $this->resolve($action, $record, ImportResolution::LinkExisting, Doctor::findOrFail($data['doctor_id'])));
    }

    private function discardAction(): Action
    {
        return Action::make('discard')
            ->label('Descartar')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Las filas de este médico no se aplicarán.')
            ->action(fn (ImportRow $record, Action $action) => $this->resolve($action, $record, ImportResolution::Discard));
    }

    private function assignCityAction(): Action
    {
        return Action::make('assignCity')
            ->label('Asignar ciudad')
            ->icon(Heroicon::OutlinedMapPin)
            ->visible(fn (ImportRow $r) => $this->issueValues($r, [RowIssue::UNKNOWN_CITY, RowIssue::AMBIGUOUS_CITY]) !== [])
            ->modalDescription(fn (ImportRow $r) => 'Se guarda "'.($this->issueValues($r, [RowIssue::UNKNOWN_CITY, RowIssue::AMBIGUOUS_CITY])[0] ?? '').'" como alias: las demás filas y las próximas cargas que lo usen se resolverán solas.')
            ->schema([
                Select::make('city_id')
                    ->label('Ciudad del catálogo')
                    ->searchable()
                    // Búsqueda en el servidor: un país puede tener miles de ciudades.
                    ->getSearchResultsUsing(fn (string $search) => $this->cityOptions($this->countryCities()
                        ->where('status', CityStatus::Active)
                        ->whereRaw('immutable_unaccent(lower(cities.name)) like ?', ['%'.Normalize::text($search).'%'])
                        ->limit(30)))
                    ->getOptionLabelUsing(fn ($value) => $this->cityOptions($this->countryCities()->whereKey($value))[$value] ?? null)
                    ->required()
                    ->helperText('Escribe para buscar. Si no existe, créala con el botón +.')
                    ->createOptionForm(fn () => $this->newCityFields())
                    ->createOptionUsing(fn (array $data) => City::create([
                        'country_id' => $this->batch()->country_id,
                        'region_id' => $data['region_id'],
                        'name' => $data['name'],
                        'slug' => $data['slug'],
                    ])->getKey())
                    ->createOptionAction(fn (Action $action) => $action
                        ->modalHeading('Nueva ciudad')
                        ->visible(fn () => auth()->user()?->can('create', City::class) ?? false)),
            ])
            ->action(function (array $data, ImportRow $record) {
                $text = $this->issueValues($record, [RowIssue::UNKNOWN_CITY, RowIssue::AMBIGUOUS_CITY])[0];
                app(MapImportValueAction::class)->city($this->batch(), $text, City::findOrFail($data['city_id']), auth()->user());
                $this->reprocess("Alias de ciudad guardado para \"{$text}\".");
            });
    }

    private function assignSpecialtyAction(): Action
    {
        return Action::make('assignSpecialty')
            ->label('Asignar especialidad')
            ->icon(Heroicon::OutlinedHeart)
            ->visible(fn (ImportRow $r) => $this->issueValues($r, [RowIssue::UNKNOWN_SPECIALTY]) !== [])
            ->schema([
                Select::make('text')
                    ->label('Texto del archivo')
                    ->options(fn (ImportRow $r) => collect($this->issueValues($r, [RowIssue::UNKNOWN_SPECIALTY]))->mapWithKeys(fn ($v) => [$v => $v]))
                    ->default(fn (ImportRow $r) => $this->issueValues($r, [RowIssue::UNKNOWN_SPECIALTY])[0] ?? null)
                    ->required(),
                Select::make('specialty_id')
                    ->label('Especialidad del catálogo')
                    ->options(fn () => Specialty::where('status', SpecialtyStatus::Active)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data) {
                app(MapImportValueAction::class)->specialty($this->batch(), $data['text'], Specialty::findOrFail($data['specialty_id']), auth()->user());
                $this->reprocess("Alias de especialidad guardado para \"{$data['text']}\".");
            });
    }

    private function bulkResolveAction(ImportResolution $resolution): BulkAction
    {
        $discard = $resolution === ImportResolution::Discard;

        return BulkAction::make($discard ? 'discardSelected' : 'createNewSelected')
            ->label($discard ? 'Descartar' : 'Crear como nuevas')
            ->icon($discard ? Heroicon::OutlinedXMark : Heroicon::OutlinedPlusCircle)
            ->color($discard ? 'danger' : 'primary')
            ->requiresConfirmation()
            ->modalHeading($discard ? 'Descartar los médicos seleccionados' : 'Crear como nuevos los médicos seleccionados')
            ->modalDescription($discard
                ? 'Los seleccionados que estén en revisión no se aplicarán. El resto no cambia.'
                : 'Cada médico seleccionado en revisión se creará como ficha nueva al aplicar, aunque se parezca a otra: un duplicado es reparable; una fusión equivocada no. Los que tienen datos por corregir siguen en revisión. Vincular a una ficha existente se decide de uno en uno.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($resolution) {
                $report = app(ResolveImportRowAction::class)->executeMany($this->batch(), $records, $resolution, auth()->user());

                $failed = array_sum($report['failed']);
                $lines = array_filter([
                    "{$report['resolved']} médicos decididos.",
                    $report['ignored'] > 0 ? "{$report['ignored']} no estaban en revisión." : null,
                    $failed > 0 ? "{$failed} siguen en revisión:" : null,
                    ...collect($report['failed'])->take(5)->map(fn (int $count, string $reason) => "· {$count} × {$reason}")->values()->all(),
                ]);

                Notification::make()
                    ->status($failed > 0 ? 'warning' : 'success')
                    ->title('Decisión registrada')
                    ->body(implode('<br>', array_map('e', $lines)))
                    ->persistent($failed > 0)
                    ->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function newCityFields(): array
    {
        return [
            Select::make('region_id')
                ->label('Región')
                ->options(fn () => Region::where('country_id', $this->batch()->country_id)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(150)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')
                ->label('Slug (URL)')
                ->helperText('Forma parte de la URL pública. Si ya existe otra ciudad con ese nombre en el país, distínguela: san-jose-alajuela.')
                ->required()
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                    if ($violation = SlugRules::violation(City::class, (string) $value, $this->batch()->country_id)) {
                        $fail($violation);
                    }
                }),
        ];
    }

    private function resolve(Action $action, ImportRow $row, ImportResolution $resolution, ?Doctor $target = null): void
    {
        try {
            app(ResolveImportRowAction::class)->execute($row, $resolution, auth()->user(), $target);
        } catch (ImportFileException $e) {
            DomainAction::notify('No se puede resolver todavía', [$e->getMessage()]);
            $action->halt();
        }

        Notification::make()->success()->title('Decisión registrada')->body('Se aplicará al pulsar "Aplicar".')->send();
    }

    private function reprocess(string $message): void
    {
        app(ImportPipeline::class)->process($this->batch(), auth()->user());
        $this->dispatch('import-batch-changed');
        Notification::make()->success()->title($message)->body('Reprocesando el lote…')->send();
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string> valores de texto del archivo con esas incidencias
     */
    private function issueValues(ImportRow $row, array $codes): array
    {
        return collect($row->validation_errors ?? [])
            ->filter(fn ($i) => in_array($i['code'], $codes, true) && filled($i['value']))
            ->pluck('value')->unique()->values()->all();
    }

    /**
     * @return Builder<City>
     */
    private function countryCities(): Builder
    {
        return City::query()->where('country_id', $this->batch()->country_id);
    }

    /**
     * @param  Builder<City>  $query
     * @return array<string, string>
     */
    private function cityOptions(Builder $query): array
    {
        return $query->with('region:id,name')->orderBy('name')->get()
            ->mapWithKeys(fn (City $c) => [$c->id => "{$c->name} ({$c->region->name})"])
            ->all();
    }

    /**
     * @return Builder<Doctor>
     */
    private function linkableDoctors(): Builder
    {
        return Doctor::query()
            ->where('country_id', $this->batch()->country_id)
            ->where('status', '<>', DoctorStatus::Merged);
    }

    /**
     * @return array<string, string>
     */
    private function doctorOptions(Builder $query): array
    {
        return $query->get()->mapWithKeys(fn (Doctor $d) => [
            $d->id => trim("{$d->first_name} {$d->last_name}").($d->license_number ? " · {$d->license_number}" : '')." · {$d->status->getLabel()}",
        ])->all();
    }
}

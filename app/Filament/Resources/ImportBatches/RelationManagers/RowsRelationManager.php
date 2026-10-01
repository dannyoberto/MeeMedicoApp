<?php

namespace App\Filament\Resources\ImportBatches\RelationManagers;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\SpecialtyStatus;
use App\Domain\Geo\Enums\CityStatus;
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
use App\Models\Specialty;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La cola de revisión del lote. Cada acción es una decisión humana auditada
 * (ResolveImportRowAction) o un mapeo que enseña al importador (MapImportValueAction).
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

    private function batch(): ImportBatch
    {
        /** @var ImportBatch */
        return $this->getOwnerRecord();
    }

    private function canResolve(): bool
    {
        return auth()->user()?->can('resolve', $this->batch()) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('row_number')
            ->defaultSort('row_number')
            ->poll('10s')
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
                $this->detailsAction(),
                ActionGroup::make([
                    $this->assignCityAction(),
                    $this->assignSpecialtyAction(),
                    $this->createNewAction(),
                    $this->linkExistingAction(),
                    $this->discardAction(),
                ])->label('Resolver')->button()->color('warning')
                    ->visible(fn (ImportRow $r) => $r->status === ImportRowStatus::NeedsReview && $this->canResolve()),
            ])
            ->emptyStateHeading('Sin filas');
    }

    private function detailsAction(): Action
    {
        return Action::make('details')
            ->label('Ver')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (ImportRow $r) => "Fila {$r->row_number}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (ImportRow $r) => view('filament.admin.imports.row-details', [
                'row' => $r,
                'candidates' => Doctor::whereKey($r->candidate_doctor_ids ?? [])->with('country:id,slug')->get(),
            ]));
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
                    ->options(fn (ImportRow $record) => $this->doctorOptions(Doctor::whereKey($record->candidate_doctor_ids ?? [])))
                    ->getSearchResultsUsing(fn (string $search) => $this->doctorOptions(Doctor::query()
                        ->where('country_id', $this->batch()->country_id)
                        ->where('status', '<>', DoctorStatus::Merged)
                        ->where(fn ($q) => $q->where('last_name', 'ilike', "%{$search}%")
                            ->orWhere('first_name', 'ilike', "%{$search}%")
                            ->orWhere('license_number', 'ilike', "%{$search}%"))
                        ->limit(20)))
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
                    ->options(fn () => City::where('country_id', $this->batch()->country_id)->where('status', CityStatus::Active)
                        ->with('region:id,name')->orderBy('name')->get()
                        ->mapWithKeys(fn (City $c) => [$c->id => "{$c->name} ({$c->region->name})"]))
                    ->searchable()
                    ->required()
                    ->helperText('¿No está? Créala en Plataforma → Ciudades y vuelve aquí.'),
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
        app(ImportPipeline::class)->process($this->batch());
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
     * @return array<string, string>
     */
    private function doctorOptions(Builder $query): array
    {
        return $query->get()->mapWithKeys(fn (Doctor $d) => [
            $d->id => trim("{$d->first_name} {$d->last_name}").($d->license_number ? " · {$d->license_number}" : '')." · {$d->status->getLabel()}",
        ])->all();
    }
}

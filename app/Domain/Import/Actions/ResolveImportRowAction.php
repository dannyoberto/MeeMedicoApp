<?php

namespace App\Domain\Import\Actions;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Import\Enums\ImportResolution;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\Exceptions\ImportFileException;
use App\Domain\Import\Support\ImportBatchCounters;
use App\Domain\Import\Support\RowGroup;
use App\Domain\Import\Support\RowIssue;
use App\Models\Doctor;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Decisión humana sobre un médico en revisión (§12.2, "resolución humana"). Se aplica
 * a todo su grupo de filas. Crear o vincular no corrige DATOS: una ciudad o especialidad
 * desconocida se resuelve asignándola (MapImportValueAction) o corrigiendo el archivo.
 */
class ResolveImportRowAction
{
    /**
     * @throws ImportFileException si la decisión no es posible
     */
    public function execute(ImportRow $row, ImportResolution $resolution, User $actor, ?Doctor $target = null): void
    {
        $batch = $row->batch;
        $group = RowGroup::from($batch->rows()->get())->first(fn (RowGroup $g) => $g->rows->contains('id', $row->getKey()));

        $this->resolveGroup($batch, $group, $resolution, $actor, $target);

        ImportBatchCounters::refresh($batch);
    }

    /**
     * La misma decisión para varios médicos de la cola de revisión. Solo "crear como
     * nueva" o "descartar": vincular exige elegir la ficha de cada uno (AGENTS.md §4,
     * nunca se fusiona en automático). Las filas se agrupan una sola vez.
     *
     * Cuenta por médico (grupo de filas), que es lo que se decide: los decididos
     * (resolved), los seleccionados que no estaban en revisión (ignored) y, por motivo,
     * los que no se pudieron decidir (failed).
     *
     * @param  Collection<int, ImportRow>  $rows  filas seleccionadas de $batch
     * @return array{resolved: int, ignored: int, failed: array<string, int>}
     */
    public function executeMany(ImportBatch $batch, Collection $rows, ImportResolution $resolution, User $actor): array
    {
        if ($resolution === ImportResolution::LinkExisting) {
            throw new InvalidArgumentException('Vincular a una ficha existente se decide médico por médico.');
        }

        $selected = $rows->modelKeys();
        $report = ['resolved' => 0, 'ignored' => 0, 'failed' => []];

        $groups = RowGroup::from($batch->rows()->get())
            ->filter(fn (RowGroup $g) => $g->rows->contains(fn (ImportRow $r) => in_array($r->getKey(), $selected, true)));

        foreach ($groups as $group) {
            if (! $group->rows->contains(fn (ImportRow $r) => $r->status === ImportRowStatus::NeedsReview)) {
                $report['ignored']++;

                continue;
            }

            try {
                $this->resolveGroup($batch, $group, $resolution, $actor);
                $report['resolved']++;
            } catch (ImportFileException $e) {
                $report['failed'][$e->getMessage()] = ($report['failed'][$e->getMessage()] ?? 0) + 1;
            }
        }

        ImportBatchCounters::refresh($batch);

        return $report;
    }

    /**
     * @throws ImportFileException
     */
    private function resolveGroup(ImportBatch $batch, RowGroup $group, ImportResolution $resolution, User $actor, ?Doctor $target = null): void
    {
        $first = $group->first();

        if ($resolution !== ImportResolution::Discard) {
            $dataErrors = collect($first->validation_errors ?? [])
                ->filter(fn ($i) => $i['level'] === RowIssue::ERROR && ! in_array($i['code'], RowIssue::DECIDABLE, true));

            if ($dataErrors->isNotEmpty()) {
                throw new ImportFileException('Antes hay que corregir: '.$dataErrors->pluck('message')->implode(' '));
            }
        }

        if ($resolution === ImportResolution::LinkExisting) {
            if (! $target || $target->country_id !== $batch->country_id || $target->status === DoctorStatus::Merged) {
                throw new ImportFileException('Elige una ficha existente del mismo país.');
            }
        }

        DB::transaction(function () use ($group, $resolution, $actor, $target, $batch) {
            foreach ($group->rows as $index => $member) {
                if (in_array($member->status, [ImportRowStatus::Applied, ImportRowStatus::Suppressed], true)) {
                    continue;
                }

                // Un consultorio con su propio error de datos sigue esperando; el resto sigue al médico.
                $ownDataError = $index > 0 && collect($member->validation_errors ?? [])->contains(fn ($i) => $i['level'] === RowIssue::ERROR);
                if ($ownDataError && $resolution !== ImportResolution::Discard) {
                    continue;
                }

                $member->forceFill([
                    'status' => $resolution === ImportResolution::Discard ? ImportRowStatus::Skipped : ImportRowStatus::Approved,
                    'resolution' => $resolution,
                    'matched_doctor_id' => $resolution === ImportResolution::LinkExisting ? $target->getKey() : null,
                    'resolved_by_user_id' => $actor->getKey(),
                    'resolved_at' => now(),
                ])->save();
            }

            activity()->performedOn($batch)->causedBy($actor)->event('import.row_resolved')
                ->withProperties(['doctor_ref' => $group->ref, 'resolution' => $resolution->value, 'doctor' => $target?->getKey()])
                ->log('import.row_resolved');
        });
    }
}

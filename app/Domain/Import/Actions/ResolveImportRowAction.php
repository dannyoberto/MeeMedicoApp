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
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        ImportBatchCounters::refresh($batch);
    }
}

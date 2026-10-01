<?php

namespace App\Domain\Import\Actions;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Support\SuppressionCheck;
use App\Domain\Import\Enums\ImportBatchStatus;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\Enums\MatchConfidence;
use App\Domain\Import\Enums\MatchType;
use App\Domain\Import\Support\ImportBatchCounters;
use App\Domain\Import\Support\RowGroup;
use App\Domain\Import\Support\RowIssue;
use App\Models\Doctor;
use App\Models\DoctorExternalReference;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Etapa 3 (§12.3): la cascada de coincidencias, por médico (grupo de filas).
 *
 *   0. supresión            → suppressed (nunca se aplica)
 *   1. referencia externa   → matched, fuerte
 *   2. licencia + país      → matched, fuerte
 *   3. teléfono / correo    → needs_review, débil
 *   4. nombre similar en la misma ciudad → needs_review, débil
 *   5. nada                 → new
 *
 * NUNCA fusiona por nombre (§12.4): un duplicado es un error barato; una fusión
 * incorrecta, irreversible. Lo débil siempre lo decide una persona.
 */
class MatchImportBatchAction
{
    /** Incidencias que produce esta etapa: se recalculan en cada ejecución. */
    private const OWN_ISSUES = [RowIssue::POSSIBLE_DUPLICATE, RowIssue::SUPPRESSION_NAME, RowIssue::DUPLICATE_LICENSE];

    public function execute(ImportBatch $batch): ImportBatch
    {
        ImportBatchCounters::refresh($batch, ImportBatchStatus::Matching);

        $groups = RowGroup::from($batch->rows()->get());

        // Dos médicos distintos del mismo archivo con la misma licencia: uno de los dos está mal.
        $licenseCounts = $groups->map(fn (RowGroup $g) => $g->first()->n_license)->filter()->countBy();

        foreach ($groups as $group) {
            if (! in_array($group->first()->status, NormalizeImportBatchAction::ELIGIBLE, true)) {
                continue; // decidido por un humano, aplicado o suprimido
            }

            DB::transaction(fn () => $this->matchGroup($group, $batch, $licenseCounts->all()));
        }

        return ImportBatchCounters::refresh($batch, ImportBatchStatus::Review);
    }

    /**
     * @param  array<string, int>  $licenseCounts
     */
    private function matchGroup(RowGroup $group, ImportBatch $batch, array $licenseCounts): void
    {
        $first = $group->first();
        $countryId = $batch->country_id;
        $phones = $group->rows->pluck('n_phone_e164')->filter()->unique()->values()->all();
        $emails = array_filter([$first->n_email]);
        $cityIds = $group->rows->pluck('n_city_id')->filter()->unique()->values()->all();

        $issues = collect($first->validation_errors ?? [])->reject(fn ($i) => in_array($i['code'], self::OWN_ISSUES, true))->values()->all();
        $match = ['type' => MatchType::None, 'confidence' => MatchConfidence::None, 'doctor' => null, 'candidates' => null];

        // 0. Supresión fuerte: la persona pidió no aparecer. Fin.
        if (SuppressionCheck::strongMatch($countryId, $first->n_license, $phones, $emails)) {
            foreach ($group->rows as $row) {
                if (in_array($row->status, NormalizeImportBatchAction::ELIGIBLE, true)) {
                    $row->forceFill(['status' => ImportRowStatus::Suppressed, 'match_type' => MatchType::None, 'match_confidence' => MatchConfidence::None])->save();
                }
            }

            return;
        }

        if (SuppressionCheck::nameMatch($countryId, $first->n_name_key)) {
            $issues[] = RowIssue::make(RowIssue::SUPPRESSION_NAME, 'Hay una supresión con este mismo nombre. Si es otra persona (homónimo), apruébala como nueva.');
        }

        if ($first->n_license && ($licenseCounts[$first->n_license] ?? 0) > 1) {
            $issues[] = RowIssue::make(RowIssue::DUPLICATE_LICENSE, "Otro médico de este archivo tiene el mismo colegiado ({$first->n_license}).", 'license_number', $first->n_license);
        }

        // 1. Referencia externa (misma fuente + mismo ID del médico).
        if (! str_starts_with($group->ref, 'fila-') && $doctor = $this->byExternalReference($batch->source, $group->ref)) {
            $match = ['type' => MatchType::ExternalRef, 'confidence' => MatchConfidence::Strong, 'doctor' => $doctor, 'candidates' => null];
        }
        // 2. Licencia en el país.
        elseif ($first->n_license && $doctor = Doctor::where('country_id', $countryId)->where('license_number', $first->n_license)->where('status', '<>', DoctorStatus::Merged)->first()) {
            $match = ['type' => MatchType::License, 'confidence' => MatchConfidence::Strong, 'doctor' => $doctor, 'candidates' => null];
        }
        // 3-4. Señales débiles: solo sugerencias para revisión humana.
        elseif ($weak = $this->weakCandidates($countryId, $phones, $emails, $first->n_name_key, $cityIds)) {
            $match = ['type' => $weak['type'], 'confidence' => MatchConfidence::Weak, 'doctor' => null, 'candidates' => $weak['ids']];
            $names = Doctor::whereKey($weak['ids'])->get()->map(fn ($d) => trim("{$d->first_name} {$d->last_name}"))->implode(', ');
            $issues[] = RowIssue::make(RowIssue::POSSIBLE_DUPLICATE, "Podría ser una ficha existente ({$names}). Vincúlala o créala como nueva.");
        }

        $firstBlocked = collect($issues)->contains(fn ($i) => $i['level'] === RowIssue::ERROR);
        $groupStatus = match (true) {
            $firstBlocked => ImportRowStatus::NeedsReview,
            $match['confidence'] === MatchConfidence::Strong => ImportRowStatus::Matched,
            default => ImportRowStatus::New,
        };

        foreach ($group->rows as $index => $row) {
            if (! in_array($row->status, NormalizeImportBatchAction::ELIGIBLE, true)) {
                continue;
            }

            $ownIssues = $index === 0 ? $issues : ($row->validation_errors ?? []);
            $ownError = collect($ownIssues)->contains(fn ($i) => $i['level'] === RowIssue::ERROR);

            // Una fila de consultorio con su propio error espera revisión; las demás siguen al
            // médico. Si el médico está en revisión, sus otros consultorios esperan sin
            // duplicar la cola (un elemento de revisión por médico).
            $status = match (true) {
                $ownError => ImportRowStatus::NeedsReview,
                $index > 0 && $groupStatus === ImportRowStatus::NeedsReview => ImportRowStatus::Normalized,
                default => $groupStatus,
            };

            $row->forceFill([
                'validation_errors' => $ownIssues ?: null,
                'status' => $status,
                'match_type' => $match['type'],
                'match_confidence' => $match['confidence'],
                'matched_doctor_id' => $match['doctor']?->getKey(),
                'candidate_doctor_ids' => $match['candidates'],
            ])->save();
        }
    }

    private function byExternalReference(string $source, string $reference): ?Doctor
    {
        $doctor = DoctorExternalReference::where('source', $source)->where('reference', $reference)->first()?->doctor;

        // Una ficha fusionada redirige a la superviviente (§13).
        while ($doctor?->status === DoctorStatus::Merged && $doctor->merged_into_doctor_id) {
            $doctor = $doctor->mergedInto;
        }

        return $doctor;
    }

    /**
     * @param  array<int, string>  $phones
     * @param  array<int, string>  $emails
     * @param  array<int, string>  $cityIds
     * @return array{type: MatchType, ids: array<int, string>}|null
     */
    private function weakCandidates(string $countryId, array $phones, array $emails, ?string $nameKey, array $cityIds): ?array
    {
        $base = fn () => Doctor::where('country_id', $countryId)->where('status', '<>', DoctorStatus::Merged);

        if ($phones !== []) {
            $ids = $base()->whereHas('contacts', fn ($q) => $q->whereIn('value_normalized', $phones))->limit(5)->pluck('id')->all();
            if ($ids !== []) {
                return ['type' => MatchType::Phone, 'ids' => $ids];
            }
        }

        if ($emails !== []) {
            $ids = $base()->whereHas('contacts', fn ($q) => $q->whereIn('value_normalized', $emails))->limit(5)->pluck('id')->all();
            if ($ids !== []) {
                return ['type' => MatchType::Email, 'ids' => $ids];
            }
        }

        if ($nameKey && $cityIds !== []) {
            // El operador % usa el índice trigram (doctors_name_trgm_gin); el umbral fino va después.
            $ids = $base()
                ->whereRaw('name_normalized % ?', [$nameKey])
                ->whereRaw('similarity(name_normalized, ?) >= ?', [$nameKey, (float) config('import.name_similarity_threshold')])
                ->whereHas('locations', fn ($q) => $q->whereIn('locations.city_id', $cityIds))
                ->orderByRaw('similarity(name_normalized, ?) desc', [$nameKey])
                ->limit(5)->pluck('id')->all();
            if ($ids !== []) {
                return ['type' => MatchType::NameCity, 'ids' => $ids];
            }
        }

        return null;
    }
}

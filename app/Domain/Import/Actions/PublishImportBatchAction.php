<?php

namespace App\Domain\Import\Actions;

use App\Domain\Directory\Actions\PublishDoctorAction;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\ImportBatch;
use App\Models\User;

/**
 * Etapa 5 (§12.3): publica, una por una, las fichas en borrador que creó el lote,
 * pasando cada una por la puerta de calidad de PublishDoctorAction. Es un paso aparte
 * a propósito: aplicar nunca publica.
 */
class PublishImportBatchAction
{
    public function __construct(private readonly PublishDoctorAction $publish) {}

    /**
     * @return array{published: int, skipped: int, reasons: array<string, int>}
     */
    public function execute(ImportBatch $batch, ?User $actor): array
    {
        $report = ['published' => 0, 'skipped' => 0, 'reasons' => []];

        Doctor::where('import_batch_id', $batch->getKey())
            ->where('status', DoctorStatus::Draft)
            ->lazyById(200)
            ->each(function (Doctor $doctor) use ($actor, &$report) {
                try {
                    $this->publish->execute($doctor, $actor);
                    $report['published']++;
                } catch (DirectoryRuleException $e) {
                    $report['skipped']++;
                    foreach ($e->details ?: [$e->getMessage()] as $reason) {
                        $report['reasons'][$reason] = ($report['reasons'][$reason] ?? 0) + 1;
                    }
                }
            });

        $summary = 'Publicación del '.now()->format('d/m/Y H:i').": {$report['published']} publicadas, {$report['skipped']} sin publicar.";
        foreach ($report['reasons'] as $reason => $count) {
            $summary .= "\n· {$count} sin: {$reason}";
        }
        $batch->update(['notes' => trim(($batch->notes ? $batch->notes."\n\n" : '').$summary)]);

        activity()->performedOn($batch)->causedBy($actor)->event('import.published')
            ->withProperties(['published' => $report['published'], 'skipped' => $report['skipped']])->log('import.published');

        return $report;
    }
}

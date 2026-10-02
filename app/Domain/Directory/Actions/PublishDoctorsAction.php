<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\User;

/**
 * Publica varias fichas, una por una, cada una por la puerta de calidad de
 * PublishDoctorAction. La que no pasa no detiene a las demás: se cuenta con su motivo.
 * La usan la publicación en lote del backoffice y la etapa 5 de la importación.
 */
class PublishDoctorsAction
{
    public function __construct(private readonly PublishDoctorAction $publish) {}

    /**
     * @param  iterable<Doctor>  $doctors
     * @return array{published: int, already: int, skipped: int, reasons: array<string, int>}
     */
    public function execute(iterable $doctors, ?User $actor): array
    {
        $report = ['published' => 0, 'already' => 0, 'skipped' => 0, 'reasons' => []];

        foreach ($doctors as $doctor) {
            if ($doctor->status === DoctorStatus::Active) {
                $report['already']++;

                continue;
            }

            try {
                $this->publish->execute($doctor, $actor);
                $report['published']++;
            } catch (DirectoryRuleException $e) {
                $report['skipped']++;
                foreach ($e->details ?: [$e->getMessage()] as $reason) {
                    $report['reasons'][$reason] = ($report['reasons'][$reason] ?? 0) + 1;
                }
            }
        }

        return $report;
    }

    /**
     * "12 publicadas, 3 ya lo estaban, 2 sin publicar."
     *
     * @param  array{published: int, already: int, skipped: int}  $report
     */
    public static function summary(array $report): string
    {
        return "{$report['published']} publicadas"
            .($report['already'] > 0 ? ", {$report['already']} ya lo estaban" : '')
            .", {$report['skipped']} sin publicar.";
    }

    /**
     * Una línea por motivo, de más a menos frecuente: "3 sin: Un contacto público".
     *
     * @param  array{reasons: array<string, int>}  $report
     * @return array<int, string>
     */
    public static function reasonLines(array $report): array
    {
        $reasons = $report['reasons'];
        arsort($reasons);

        return array_map(fn (string $reason, int $count) => "{$count} sin: {$reason}", array_keys($reasons), $reasons);
    }
}

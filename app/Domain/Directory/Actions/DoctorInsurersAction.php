<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\InsurerStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\Insurer;
use App\Models\User;

/**
 * Aseguradoras por las que atiende un médico (DATABASE.md §9.14). Solo aseguradoras
 * activas de su país: la FK no puede imponerlo sin ensanchar doctors. No condicionan la
 * publicación, pero se mostrarán en la ficha: se auditan y purgan como el resto del agregado.
 */
class DoctorInsurersAction
{
    /**
     * @throws DirectoryRuleException
     */
    public function attach(Doctor $doctor, Insurer $insurer, ?User $actor): void
    {
        $this->assertAttachable($doctor, $insurer);

        if ($doctor->insurers()->whereKey($insurer->getKey())->exists()) {
            return;
        }

        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'insurers', 'op' => 'attached', 'insurer' => $insurer->name],
            fn () => $doctor->insurers()->attach($insurer->getKey()));
    }

    public function detach(Doctor $doctor, Insurer $insurer, ?User $actor): void
    {
        if (! $doctor->insurers()->whereKey($insurer->getKey())->exists()) {
            return;
        }

        DoctorAggregateChange::apply($doctor, $actor, ['part' => 'insurers', 'op' => 'detached', 'insurer' => $insurer->name],
            fn () => $doctor->insurers()->detach($insurer->getKey()));
    }

    /**
     * Asigna (o quita) una aseguradora a muchos médicos: el caso de la aseguradora que
     * entrega su red. Cada médico pasa por attach/detach; el que no puede no detiene al resto.
     *
     * @param  iterable<Doctor>  $doctors
     * @return array{changed: int, unchanged: int, skipped: int, reasons: array<string, int>}
     */
    public function applyToMany(Insurer $insurer, iterable $doctors, bool $attach, ?User $actor): array
    {
        $report = ['changed' => 0, 'unchanged' => 0, 'skipped' => 0, 'reasons' => []];

        foreach ($doctors as $doctor) {
            $has = $doctor->insurers()->whereKey($insurer->getKey())->exists();

            if ($has === $attach) {
                $report['unchanged']++;

                continue;
            }

            try {
                $attach ? $this->attach($doctor, $insurer, $actor) : $this->detach($doctor, $insurer, $actor);
                $report['changed']++;
            } catch (DirectoryRuleException $e) {
                $report['skipped']++;
                $report['reasons'][$e->getMessage()] = ($report['reasons'][$e->getMessage()] ?? 0) + 1;
            }
        }

        return $report;
    }

    /**
     * "12 asignadas, 3 ya la tenían, 2 omitidas." / "… quitadas, … no la tenían, …"
     *
     * @param  array{changed: int, unchanged: int, skipped: int, reasons: array<string, int>}  $report
     * @return array<int, string>
     */
    public static function reportLines(array $report, bool $attach): array
    {
        $reasons = $report['reasons'];
        arsort($reasons);

        return [
            ($attach ? "{$report['changed']} asignadas" : "{$report['changed']} quitadas")
                .($report['unchanged'] > 0 ? ', '.$report['unchanged'].($attach ? ' ya la tenían' : ' no la tenían') : '')
                .", {$report['skipped']} omitidas.",
            ...array_map(fn (string $reason, int $count) => "{$count}: {$reason}", array_keys($reasons), $reasons),
        ];
    }

    /**
     * @throws DirectoryRuleException
     */
    private function assertAttachable(Doctor $doctor, Insurer $insurer): void
    {
        if ($insurer->status !== InsurerStatus::Active) {
            throw new DirectoryRuleException("La aseguradora {$insurer->name} está inactiva.");
        }

        if ($insurer->country_id !== $doctor->country_id) {
            throw new DirectoryRuleException('La aseguradora es de otro país que el médico.');
        }

        if ($doctor->status === DoctorStatus::Merged) {
            throw new DirectoryRuleException('La ficha fue fusionada con otra.');
        }
    }
}

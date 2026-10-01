<?php

namespace App\Domain\Import\Support;

use App\Domain\Geo\Support\Normalize;
use App\Models\ImportRow;
use Illuminate\Support\Collection;

/**
 * Las filas de un mismo médico dentro de un lote (mismo "ID del médico"). La plantilla
 * tiene una fila por consultorio: los datos del médico pueden venir en cualquiera.
 *
 * Valor efectivo de un campo del médico = el primero no vacío por orden de fila. Si
 * otra fila trae un valor DISTINTO, es un conflicto: nunca se elige uno en silencio.
 */
final class RowGroup
{
    /** Campos del médico (se repiten por fila); el resto son del consultorio. */
    public const DOCTOR_FIELDS = [
        'first_name', 'last_name', 'professional_name', 'gender', 'license_number',
        'specialty_1', 'specialty_2', 'specialty_3', 'languages', 'email', 'website',
        'headline', 'bio', 'education', 'experience',
    ];

    /**
     * @param  Collection<int, ImportRow>  $rows  ordenadas por row_number
     */
    public function __construct(public readonly string $ref, public readonly Collection $rows) {}

    public function first(): ImportRow
    {
        return $this->rows->first();
    }

    public function value(string $field): ?string
    {
        foreach ($this->rows as $row) {
            $value = self::clean($row->raw_payload[$field] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> campo => valores distintos encontrados ("A" / "B")
     */
    public function conflicts(): array
    {
        $conflicts = [];

        foreach (self::DOCTOR_FIELDS as $field) {
            $values = $this->rows
                ->map(fn (ImportRow $r) => self::clean($r->raw_payload[$field] ?? null))
                ->filter()
                ->unique(fn (string $v) => Normalize::text($v));

            if ($values->count() > 1) {
                $conflicts[$field] = $values->map(fn ($v) => "\"{$v}\"")->implode(' / ');
            }
        }

        return $conflicts;
    }

    public static function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Agrupa filas por ID del médico; una fila sin ID es su propio grupo.
     *
     * @param  iterable<ImportRow>  $rows
     * @return Collection<string, RowGroup>
     */
    public static function from(iterable $rows): Collection
    {
        return collect($rows)
            ->sortBy('row_number')
            ->groupBy(fn (ImportRow $r) => self::clean($r->raw_payload['doctor_ref'] ?? null) ?? "fila-{$r->row_number}")
            ->map(fn (Collection $rows, string $ref) => new self($ref, $rows->values()));
    }
}

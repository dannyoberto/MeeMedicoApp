<?php

namespace App\Domain\Import\Support;

/**
 * Una incidencia de una fila de importación, guardada en import_rows.validation_errors.
 *
 * - error: impide aplicar la fila hasta que un humano la resuelva.
 * - conflict: el archivo trae un valor distinto del que ya tiene la ficha; no se
 *   sobrescribe (decisión de la Etapa 6) y queda anotado.
 * - warning: un dato secundario no se pudo usar (teléfono inválido, idioma desconocido);
 *   se descarta ese dato y la fila sigue. No bloquea.
 */
final class RowIssue
{
    public const ERROR = 'error';

    public const CONFLICT = 'conflict';

    public const WARNING = 'warning';

    /**
     * Errores que una decisión humana (crear nueva / vincular) resuelve. Los demás
     * errores son de DATOS (ciudad o especialidad desconocida, campo obligatorio vacío):
     * se corrigen asignando el valor del catálogo o arreglando el archivo.
     */
    public const DECIDABLE = [
        self::GROUP_CONFLICT, self::DUPLICATE_LICENSE, self::POSSIBLE_DUPLICATE, self::SUPPRESSION_NAME,
    ];

    // Códigos estables: el backoffice los usa para ofrecer la acción adecuada.
    public const MISSING = 'missing';

    public const UNKNOWN_CITY = 'unknown_city';

    public const AMBIGUOUS_CITY = 'ambiguous_city';

    public const UNKNOWN_SPECIALTY = 'unknown_specialty';

    public const UNKNOWN_VALUE = 'unknown_value';

    public const INVALID_PHONE = 'invalid_phone';

    public const INVALID_EMAIL = 'invalid_email';

    public const GROUP_CONFLICT = 'group_conflict';

    public const DUPLICATE_LICENSE = 'duplicate_license';

    public const POSSIBLE_DUPLICATE = 'possible_duplicate';

    public const SUPPRESSION_NAME = 'suppression_name';

    public const EXISTING_VALUE = 'existing_value';

    public const APPLY_FAILED = 'apply_failed';

    /**
     * @return array{code: string, field: ?string, value: ?string, message: string, level: string}
     */
    public static function make(string $code, string $message, ?string $field = null, ?string $value = null, string $level = self::ERROR): array
    {
        return compact('code', 'field', 'value', 'message', 'level');
    }
}

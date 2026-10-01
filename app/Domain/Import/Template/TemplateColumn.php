<?php

namespace App\Domain\Import\Template;

/**
 * Una columna de la plantilla de carga masiva.
 */
final readonly class TemplateColumn
{
    public const GROUP_DOCTOR = 'doctor';

    public const GROUP_LOCATION = 'location';

    public const LIST_SPECIALTIES = 'especialidades';

    public const LIST_GENDERS = 'generos';

    public const LIST_LOCATION_TYPES = 'tipos_consultorio';

    public const LIST_CITIES = 'ciudades';

    public const LIST_REGIONS = 'regiones';

    /**
     * @param  string  $key  clave interna estable (la usa el importador)
     * @param  string  $label  encabezado visible; el importador lo reconoce ignorando '*' y mayúsculas
     * @param  bool  $text  forzar formato texto (conserva ceros iniciales y evita notación científica)
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public string $note,
        public bool $required = false,
        public bool $recommended = false,
        public ?string $list = null,
        public bool $text = false,
        public int $width = 18,
    ) {}

    public function header(): string
    {
        return $this->required ? "{$this->label} *" : $this->label;
    }
}

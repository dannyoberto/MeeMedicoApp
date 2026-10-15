<?php

namespace App\Domain\Search;

use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\Specialty;

/**
 * Lo que el paciente busca, ya resuelto contra los catálogos. Lo producen las rutas
 * públicas (slugs de la URL) y QueryInterpreter (texto libre); lo consume
 * DoctorSearchService. Sin dependencia del motor: sirve igual para Meilisearch.
 *
 * Las subespecialidades no tienen página propia (ARQUITECTURA.md §12 #2): se buscan
 * como filtro dentro de su especialidad padre. Por eso $specialty es siempre de primer
 * nivel y $subspecialty, si existe, es hija suya.
 */
final readonly class SearchCriteria
{
    public function __construct(
        public Country $country,
        public ?Specialty $specialty = null,
        public ?Specialty $subspecialty = null,
        public ?City $city = null,
        public ?Language $language = null,
        public ?string $text = null,
        public int $page = 1,
    ) {}

    /**
     * Coloca una especialidad cualquiera en su sitio: si es hija, el padre va en
     * $specialty y ella en $subspecialty.
     *
     * @return array{0: Specialty|null, 1: Specialty|null}
     */
    public static function specialtyPair(?Specialty $specialty): array
    {
        if ($specialty?->parent_id === null) {
            return [$specialty, null];
        }

        return [$specialty->parent, $specialty];
    }

    /**
     * Especialidad y ciudad sin nada más: tiene una URL canónica de listado.
     */
    public function isListing(): bool
    {
        return $this->specialty !== null && $this->city !== null && $this->text === null;
    }

    public function isEmpty(): bool
    {
        return $this->specialty === null && $this->city === null
            && $this->language === null && $this->text === null;
    }

    /**
     * Una copia sin uno de los criterios: 'specialty' (quita también la subespecialidad),
     * 'subspecialty', 'city', 'language' o 'text'. Vuelve a la primera página.
     */
    public function without(string $criterion): self
    {
        return new self(
            country: $this->country,
            specialty: $criterion === 'specialty' ? null : $this->specialty,
            subspecialty: in_array($criterion, ['specialty', 'subspecialty'], true) ? null : $this->subspecialty,
            city: $criterion === 'city' ? null : $this->city,
            language: $criterion === 'language' ? null : $this->language,
            text: $criterion === 'text' ? null : $this->text,
        );
    }

    public function withPage(int $page): self
    {
        return new self(
            $this->country, $this->specialty, $this->subspecialty,
            $this->city, $this->language, $this->text, max(1, $page),
        );
    }
}

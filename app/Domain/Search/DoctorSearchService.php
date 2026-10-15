<?php

namespace App\Domain\Search;

use App\Models\City;
use App\Models\Country;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Búsqueda pública de médicos (ARQUITECTURA.md §7). Los controladores y la API dependen
 * de esta interfaz, nunca del motor: pasar a Meilisearch en Fase 2 es otro driver.
 *
 * Solo devuelve fichas publicadas del país. Un médico aparece en la ciudad de su
 * ubicación PRINCIPAL (MODELO-DOMINIO.md §2.4), igual que en DoctorPublicPaths.
 */
interface DoctorSearchService
{
    /**
     * @return LengthAwarePaginator<int, Doctor>
     */
    public function search(SearchCriteria $criteria, int $perPage = 20): LengthAwarePaginator;

    /**
     * Ciudades donde hay resultados para el criterio (sin su ciudad), con el número de
     * médicos, de más a menos.
     *
     * @return Collection<int, array{city: City, total: int}>
     */
    public function cityFacets(SearchCriteria $criteria): Collection;

    /**
     * Especialidades de primer nivel con médicos publicados en el país, de más a menos.
     * Una subespecialidad suma en su padre.
     *
     * @return Collection<int, array{specialty: Specialty, total: int}>
     */
    public function specialtyFacets(Country $country): Collection;
}

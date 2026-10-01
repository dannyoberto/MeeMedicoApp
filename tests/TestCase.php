<?php

namespace Tests;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Los seeds de Fase 1 (permisos, países, idiomas, especialidades) se cargan una
     * vez tras migrar: los tests parten del mismo catálogo que producción.
     */
    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;
}

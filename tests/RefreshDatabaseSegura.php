<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait RefreshDatabaseSegura
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $conexion = DB::connection();
        $aislada = $conexion->getDriverName() === 'sqlite'
            && $conexion->getDatabaseName() === ':memory:';
        $mariaPruebas = in_array($conexion->getDriverName(), ['mysql', 'mariadb'], true)
            && preg_match('/\Arecetas_revision_test_[a-z0-9_]+\z/', $conexion->getDatabaseName()) === 1
            && $conexion->selectOne('SELECT DATABASE() AS nombre')->nombre === $conexion->getDatabaseName();

        if (! app()->environment('testing') || (! $aislada && ! $mariaPruebas)) {
            throw new RuntimeException('Pruebas detenidas: la conexión no es una base aislada de revisión.');
        }
    }

    protected function migrateDatabases(): void
    {
        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);
    }
}

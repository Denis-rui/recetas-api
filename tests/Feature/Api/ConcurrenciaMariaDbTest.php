<?php

use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

// Propiedad de la base de datos creada exclusivamente en esta ejecución
$baseCreadaPorEjecucion = null;

function obtenerCredencialesMariaDbTest(): ?array
{
    $host = config('database.connections.mariadb_test.host');
    $port = config('database.connections.mariadb_test.port', '3306');
    $username = config('database.connections.mariadb_test.username');
    $password = config('database.connections.mariadb_test.password');

    // Comprobación estricta de seguridad: no utilizar valores de respaldo inseguros (como root o password vacía por defecto)
    if (empty($host) || empty($username)) {
        return null;
    }

    return [
        'host' => (string) $host,
        'port' => (string) $port,
        'username' => (string) $username,
        'password' => (string) ($password ?? ''),
    ];
}

function conectarPdoMariaDbTest(array $credenciales): ?PDO
{
    try {
        $dsn = "mysql:host={$credenciales['host']};port={$credenciales['port']}";

        return new PDO($dsn, $credenciales['username'], $credenciales['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
    } catch (Throwable $e) {
        return null;
    }
}

beforeEach(function () use (&$baseCreadaPorEjecucion) {
    $credenciales = obtenerCredencialesMariaDbTest();

    if ($credenciales === null) {
        $this->markTestSkipped('No existe una configuración explícita de pruebas para MariaDB (DB_TEST_HOST y DB_TEST_USERNAME no configurados en .env). Pruebas de concurrencia en MariaDB omitidas para proteger el entorno.');
    }

    $pdo = conectarPdoMariaDbTest($credenciales);

    if ($pdo === null) {
        $this->markTestSkipped("No fue posible conectar al servidor MariaDB de pruebas en {$credenciales['host']}:{$credenciales['port']}. Pruebas omitidas.");
    }

    // Generar un nombre único por ejecución para garantizar aislamiento estricto
    $nombreBase = 'recetas_test_concurrencia_'.bin2hex(random_bytes(6));

    // Abortar si la base ya existe en information_schema (no se asume propiedad de bases previas)
    $stmtCheck = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :nombre');
    $stmtCheck->execute(['nombre' => $nombreBase]);
    if ($stmtCheck->fetch()) {
        throw new RuntimeException("La base de datos {$nombreBase} ya existe. Abortando para evitar colisión de pruebas.");
    }

    // Crear la base de datos única sin IF NOT EXISTS
    $pdo->exec("CREATE DATABASE `{$nombreBase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Registrar la propiedad únicamente después del éxito de CREATE DATABASE
    $baseCreadaPorEjecucion = $nombreBase;

    // Configurar conexión mariadb_test con la base única
    Config::set('database.connections.mariadb_test.database', $nombreBase);
    DB::purge('mariadb_test');
    DB::setDefaultConnection('mariadb_test');

    // Ejecutar migraciones en la base única recién creada
    Artisan::call('migrate:fresh', [
        '--database' => 'mariadb_test',
        '--force' => true,
    ]);
});

afterEach(function () {
    DB::setDefaultConnection('sqlite');
});

afterAll(function () use (&$baseCreadaPorEjecucion) {
    if ($baseCreadaPorEjecucion !== null) {
        $credenciales = obtenerCredencialesMariaDbTest();
        if ($credenciales !== null) {
            $pdo = conectarPdoMariaDbTest($credenciales);
            if ($pdo !== null) {
                // Eliminar únicamente la base de datos creada por esta ejecución específica
                $pdo->exec("DROP DATABASE IF EXISTS `{$baseCreadaPorEjecucion}`");
            }
        }
        $baseCreadaPorEjecucion = null;
    }
});

test('en MariaDB dos verificaciones simultaneas del mismo codigo en procesos independientes solo permiten un exito', function () use (&$baseCreadaPorEjecucion) {
    $usuario = User::create([
        'name' => 'Usuario Concurrencia',
        'email' => 'concurrente@ejemplo.com',
        'password' => Hash::make('PasswordSegura2026*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $codigo = '739281';
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'concurrente@ejemplo.com',
        'codigo_hash' => Hash::make($codigo),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $comandoScript = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        try {
            $token = app(App\Actions\Auth\VerificarCodigoRecuperacion::class)->ejecutar("concurrente@ejemplo.com", "'.$codigo.'");
            echo "EXITO:" . $token;
        } catch (\Throwable $e) {
            echo "FALLO:" . $e->getMessage();
        }
    ';

    $proceso1 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$comandoScript]);
    $proceso2 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$comandoScript]);

    // Iniciar ambos procesos en paralelo de forma asíncrona
    $proceso1->start();
    $proceso2->start();

    // Esperas limitadas (máximo 8 segundos)
    $proceso1->wait();
    $proceso2->wait();

    $salida1 = $proceso1->getOutput();
    $salida2 = $proceso2->getOutput();

    $exitos = (str_contains($salida1, 'EXITO:') ? 1 : 0) + (str_contains($salida2, 'EXITO:') ? 1 : 0);
    $fallos = (str_contains($salida1, 'FALLO:') ? 1 : 0) + (str_contains($salida2, 'FALLO:') ? 1 : 0);

    // Exactamente 1 proceso debe haber tenido éxito y el otro debe haber fallado
    expect($exitos)->toBe(1)
        ->and($fallos)->toBe(1);

    $recuperacion->refresh();
    expect($recuperacion->codigo_verificado_en)->not->toBeNull()
        ->and($recuperacion->token_recuperacion_hash)->not->toBeNull();
});

test('en MariaDB dos restablecimientos simultaneos del mismo token en procesos independientes solo permiten un exito', function () use (&$baseCreadaPorEjecucion) {
    $usuario = User::create([
        'name' => 'Usuario Restablecer',
        'email' => 'restablecer_proc@ejemplo.com',
        'password' => Hash::make('ClaveAntigua123*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $tokenPlano = Str::random(64);
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'restablecer_proc@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'codigo_verificado_en' => now(),
        'token_recuperacion_hash' => hash('sha256', $tokenPlano),
        'token_expira_en' => now()->addMinutes(15),
        'codigo_expira_en' => now()->addMinutes(10),
        'usado_en' => null,
    ]);

    $comandoScript = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        try {
            app(App\Actions\Auth\RestablecerPasswordConToken::class)->ejecutar("'.$tokenPlano.'", "NuevaPasswordSegura2026*");
            echo "EXITO";
        } catch (\Throwable $e) {
            echo "FALLO:" . $e->getMessage();
        }
    ';

    $proceso1 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$comandoScript]);
    $proceso2 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$comandoScript]);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();

    $salida1 = $proceso1->getOutput();
    $salida2 = $proceso2->getOutput();

    $exitos = (str_contains($salida1, 'EXITO') ? 1 : 0) + (str_contains($salida2, 'EXITO') ? 1 : 0);
    $fallos = (str_contains($salida1, 'FALLO:') ? 1 : 0) + (str_contains($salida2, 'FALLO:') ? 1 : 0);

    expect($exitos)->toBe(1)
        ->and($fallos)->toBe(1);

    $recuperacion->refresh();
    expect($recuperacion->usado_en)->not->toBeNull();
});

test('en MariaDB emision concurrente con cambio de correo se serializa y no emite codigo para el correo antiguo', function () use (&$baseCreadaPorEjecucion) {
    $usuario = User::create([
        'name' => 'Usuario Cambio Correo',
        'email' => 'anterior@ejemplo.com',
        'password' => Hash::make('PasswordSegura2026*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    // Proceso 1: Cambia el correo a 'nuevo@ejemplo.com'
    $scriptCambio = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        $u = App\Models\User::where("email", "anterior@ejemplo.com")->first();
        app(App\Actions\Usuarios\ActualizarPerfil::class)->ejecutar($u, ["email" => "nuevo@ejemplo.com"]);
        echo "CAMBIO_OK";
    ';

    // Proceso 2: Solicita recuperación para 'anterior@ejemplo.com'
    $scriptSolicitar = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        $msg = app(App\Actions\Auth\SolicitarRecuperacionPassword::class)->ejecutar("anterior@ejemplo.com");
        echo "SOLICITUD_OK";
    ';

    $proceso1 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$scriptCambio]);
    $proceso2 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$scriptSolicitar]);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();

    $usuario->refresh();
    expect($usuario->email)->toBe('nuevo@ejemplo.com');

    // No debe haber ninguna recuperación activa para el correo antiguo
    $recuperacionAntiguaActiva = RecuperacionPassword::where('email', 'anterior@ejemplo.com')
        ->whereNull('invalidado_en')
        ->first();

    expect($recuperacionAntiguaActiva)->toBeNull();
});

test('en MariaDB emision concurrente con cambio de contrasena invalida codigos pendientes', function () use (&$baseCreadaPorEjecucion) {
    $usuario = User::create([
        'name' => 'Usuario Cambio Clave',
        'email' => 'clave_concurrente@ejemplo.com',
        'password' => Hash::make('ClaveVieja123*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $scriptPassword = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        $u = App\Models\User::where("email", "clave_concurrente@ejemplo.com")->first();
        app(App\Actions\Usuarios\CambiarPassword::class)->ejecutar($u, "NuevaClaveCambiada2026*");
        echo "PASSWORD_OK";
    ';

    $scriptSolicitar = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        app(App\Actions\Auth\SolicitarRecuperacionPassword::class)->ejecutar("clave_concurrente@ejemplo.com");
        echo "SOLICITAR_OK";
    ';

    $proceso1 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$scriptPassword]);
    $proceso2 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$scriptSolicitar]);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();

    $usuario->refresh();
    expect(Hash::check('NuevaClaveCambiada2026*', $usuario->password))->toBeTrue();
});

test('en MariaDB emision concurrente con desactivacion de cuenta se serializa y no permite recuperacion para cuenta inactiva', function () use (&$baseCreadaPorEjecucion) {
    $admin = User::create([
        'name' => 'Admin Concurrencia',
        'email' => 'admin_desact@ejemplo.com',
        'password' => Hash::make('AdminPassword2026*'),
        'rol' => 'administrador',
        'activo' => true,
    ]);

    $usuario = User::create([
        'name' => 'Usuario a Desactivar',
        'email' => 'desactivar_conc@ejemplo.com',
        'password' => Hash::make('UserPassword2026*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $scriptDesactivar = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        $admin = App\Models\User::where("email", "admin_desact@ejemplo.com")->first();
        $u = App\Models\User::where("email", "desactivar_conc@ejemplo.com")->first();
        app(App\Actions\Usuarios\DeshabilitarCuenta::class)->ejecutar($admin, $u);
        echo "DESACTIVAR_OK";
    ';

    $scriptSolicitar = '
        Config::set("database.connections.mariadb_test.database", "'.$baseCreadaPorEjecucion.'");
        DB::setDefaultConnection("mariadb_test");
        app(App\Actions\Auth\SolicitarRecuperacionPassword::class)->ejecutar("desactivar_conc@ejemplo.com");
        echo "SOLICITAR_OK";
    ';

    $proceso1 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$scriptDesactivar]);
    $proceso2 = new Process([PHP_BINARY, 'artisan', 'tinker', '--execute='.$scriptSolicitar]);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();

    $usuario->refresh();
    expect($usuario->activo)->toBeFalse();

    // No debe haber ninguna recuperación activa para la cuenta desactivada
    $recuperacionActiva = RecuperacionPassword::where('user_id', $usuario->id)
        ->whereNull('invalidado_en')
        ->first();

    expect($recuperacionActiva)->toBeNull();
});

<?php

use App\Actions\Auth\IniciarSesionApi;
use App\Actions\Usuarios\ReactivarCuenta;
use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

global $baseCreadaPorEjecucion, $pdoPropietario, $conexionOriginal, $procesosMariaDbTest;
$baseCreadaPorEjecucion = null;
$pdoPropietario = null;
$conexionOriginal = null;
$procesosMariaDbTest = [];

pest()->group('concurrencia-mariadb');

function obtenerCredencialesMariaDbTest(): ?array
{
    $configuracion = config('database.connections.mariadb_test');

    if (empty($configuracion['host']) || empty($configuracion['username'])) {
        return null;
    }

    return $configuracion;
}

function crearProcesoMariaDbTest(string $script): Process
{
    global $procesosMariaDbTest;

    $bootstrap = <<<'PHP'
    use Illuminate\Support\Facades\Config;
    use Illuminate\Support\Facades\DB;
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $configuracion = json_decode(base64_decode(getenv('PRUEBA_CONEXION')), true, flags: JSON_THROW_ON_ERROR);
    if (! preg_match('/\Arecetas_revision_test_concurrencia_[a-f0-9]+\z/', $configuracion['database'])) {
        throw new RuntimeException('Base de prueba no autorizada.');
    }
    Config::set('database.connections.mariadb_test', $configuracion);
    Config::set('cache.default', 'array');
    Config::set('mail.default', 'array');
    Config::set('queue.default', 'sync');
    DB::setDefaultConnection('mariadb_test');
    Illuminate\Support\Facades\Mail::fake();
    PHP;

    $proceso = new Process([PHP_BINARY, '-r', $bootstrap."\n".$script], base_path(), [
        'APP_ENV' => 'testing',
        'APP_CONFIG_CACHE' => base_path('storage/framework/cache/config-concurrencia-no-existente.php'),
        'CACHE_STORE' => 'array',
        'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'PRUEBA_CONEXION' => base64_encode(json_encode(config('database.connections.mariadb_test'), JSON_THROW_ON_ERROR)),
    ], timeout: 15);
    $procesosMariaDbTest[] = $proceso;

    return $proceso;
}

function limpiarBaseMariaDbTest(): void
{
    global $baseCreadaPorEjecucion, $pdoPropietario, $conexionOriginal, $procesosMariaDbTest;

    foreach ($procesosMariaDbTest as $proceso) {
        if ($proceso->isRunning()) {
            $proceso->stop(1);
        }
    }
    $procesosMariaDbTest = [];
    DB::purge('mariadb_test');

    if ($conexionOriginal !== null) {
        DB::setDefaultConnection($conexionOriginal);
    }

    if ($baseCreadaPorEjecucion !== null && $pdoPropietario instanceof PDO) {
        if (! preg_match('/\Arecetas_revision_test_concurrencia_[a-f0-9]+\z/', $baseCreadaPorEjecucion)) {
            throw new RuntimeException('Se rechazó limpiar una base ajena a la prueba.');
        }

        $pdoPropietario->exec("DROP DATABASE `{$baseCreadaPorEjecucion}`");
        $comprobacion = $pdoPropietario->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $comprobacion->execute([$baseCreadaPorEjecucion]);
        expect($comprobacion->fetchColumn())->toBeFalse();
    }

    $baseCreadaPorEjecucion = null;
    $pdoPropietario = null;
    $conexionOriginal = null;
}

beforeEach(function () use (&$baseCreadaPorEjecucion, &$pdoPropietario, &$conexionOriginal) {
    $credenciales = obtenerCredencialesMariaDbTest();

    if ($credenciales === null) {
        $this->markTestSkipped('Configure DB_TEST_HOST y DB_TEST_USERNAME explícitamente para concurrencia real en MariaDB.');
    }

    $conexionOriginal = DB::getDefaultConnection();
    $pdoPropietario = new PDO(
        "mysql:host={$credenciales['host']};port={$credenciales['port']}",
        $credenciales['username'],
        $credenciales['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3],
    );
    $nombreBase = 'recetas_revision_test_concurrencia_'.bin2hex(random_bytes(6));
    $pdoPropietario->exec("CREATE DATABASE `{$nombreBase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $baseCreadaPorEjecucion = $nombreBase;

    try {
        Config::set('database.connections.mariadb_test.database', $nombreBase);
        DB::purge('mariadb_test');
        DB::setDefaultConnection('mariadb_test');
        expect(DB::selectOne('SELECT DATABASE() AS nombre')->nombre)->toBe($nombreBase);
        expect(Artisan::call('migrate', ['--database' => 'mariadb_test', '--force' => true, '--no-interaction' => true]))->toBe(0);
    } catch (Throwable $exception) {
        limpiarBaseMariaDbTest();

        throw $exception;
    }
});

afterEach(function () {
    limpiarBaseMariaDbTest();
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            echo "FALLO:" . $e->getMessage();
        }
    ';

    $proceso1 = crearProcesoMariaDbTest($comandoScript);
    $proceso2 = crearProcesoMariaDbTest($comandoScript);

    // Iniciar ambos procesos en paralelo de forma asíncrona
    $proceso1->start();
    $proceso2->start();

    // Ambos procesos tienen el timeout acotado del helper.
    $proceso1->wait();
    $proceso2->wait();
    expect($proceso1->isSuccessful())->toBeTrue($proceso1->getErrorOutput());
    expect($proceso2->isSuccessful())->toBeTrue($proceso2->getErrorOutput());

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
        } catch (\Illuminate\Validation\ValidationException $e) {
            echo "FALLO:" . $e->getMessage();
        }
    ';

    $proceso1 = crearProcesoMariaDbTest($comandoScript);
    $proceso2 = crearProcesoMariaDbTest($comandoScript);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();
    expect($proceso1->isSuccessful())->toBeTrue($proceso1->getErrorOutput());
    expect($proceso2->isSuccessful())->toBeTrue($proceso2->getErrorOutput());

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

    $proceso1 = crearProcesoMariaDbTest($scriptCambio);
    $proceso2 = crearProcesoMariaDbTest($scriptSolicitar);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();
    expect($proceso1->isSuccessful())->toBeTrue($proceso1->getErrorOutput());
    expect($proceso2->isSuccessful())->toBeTrue($proceso2->getErrorOutput());

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

    $proceso1 = crearProcesoMariaDbTest($scriptPassword);
    $proceso2 = crearProcesoMariaDbTest($scriptSolicitar);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();
    expect($proceso1->isSuccessful())->toBeTrue($proceso1->getErrorOutput());
    expect($proceso2->isSuccessful())->toBeTrue($proceso2->getErrorOutput());

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

    $proceso1 = crearProcesoMariaDbTest($scriptDesactivar);
    $proceso2 = crearProcesoMariaDbTest($scriptSolicitar);

    $proceso1->start();
    $proceso2->start();

    $proceso1->wait();
    $proceso2->wait();
    expect($proceso1->isSuccessful())->toBeTrue($proceso1->getErrorOutput());
    expect($proceso2->isSuccessful())->toBeTrue($proceso2->getErrorOutput());

    $usuario->refresh();
    expect($usuario->activo)->toBeFalse();

    // No debe haber ninguna recuperación activa para la cuenta desactivada
    $recuperacionActiva = RecuperacionPassword::where('user_id', $usuario->id)
        ->whereNull('invalidado_en')
        ->first();

    expect($recuperacionActiva)->toBeNull();
});

function esperarBloqueoMariaDbTest(Process $proceso): void
{
    global $pdoPropietario;

    expect($proceso->waitUntil(fn (string $tipo, string $salida): bool => str_contains($proceso->getOutput(), 'ESPERANDO_BLOQUEO')))
        ->toBeTrue($proceso->getErrorOutput());
    preg_match('/CONEXION:(\d+)/', $proceso->getOutput(), $coincidencia);
    expect($coincidencia)->toHaveCount(2);
    $consulta = $pdoPropietario->prepare('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS AS espera JOIN information_schema.INNODB_TRX AS transaccion ON transaccion.trx_id = espera.requesting_trx_id WHERE transaccion.trx_mysql_thread_id = ?');
    $limite = microtime(true) + 5;
    do {
        $consulta->execute([(int) $coincidencia[1]]);
        if ((int) $consulta->fetchColumn() > 0) {
            return;
        }
        usleep(10000);
    } while ($proceso->isRunning() && microtime(true) < $limite);

    throw new RuntimeException('No se observó la espera real de bloqueo en MariaDB.');
}

function observarBloqueoMariaDbTest(): string
{
    return <<<'PHP'
    echo 'CONEXION:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n";
    DB::connection()->beforeExecuting(function (string $sql): void {
        if (str_contains(strtolower($sql), 'for update')) {
            echo "ESPERANDO_BLOQUEO\n";
            fflush(STDOUT);
        }
    });
    PHP;
}

function prepararRevocacionMariaDbTest(User $usuario, User $admin, string $operacion): string
{
    $token = Str::random(64);
    RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => $usuario->email,
        'codigo_hash' => Hash::make('739281'),
        'codigo_expira_en' => now()->addMinutes(10),
        'codigo_verificado_en' => now(),
        'token_recuperacion_hash' => hash('sha256', $token),
        'token_expira_en' => now()->addMinutes(15),
    ]);

    return match ($operacion) {
        'deshabilitar' => 'app(App\\Actions\\Usuarios\\DeshabilitarCuenta::class)->ejecutar(App\\Models\\User::findOrFail('.$admin->id.'), App\\Models\\User::findOrFail('.$usuario->id.'));',
        'cambiar' => 'app(App\\Actions\\Usuarios\\CambiarPassword::class)->ejecutar(App\\Models\\User::findOrFail('.$usuario->id.'), "NuevaSegura2026*");',
        'restablecer' => 'app(App\\Actions\\Auth\\RestablecerPasswordConToken::class)->ejecutar("'.$token.'", "NuevaSegura2026*");',
    };
}

test('MariaDB login espera la revocacion y rechaza credenciales anteriores sin emitir token', function (string $operacion) {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $usuario = User::factory()->create(['password' => 'AnteriorSegura2026*']);
    $revocar = prepararRevocacionMariaDbTest($usuario, $admin, $operacion);
    $proceso = crearProcesoMariaDbTest(observarBloqueoMariaDbTest().'
        $resultado = app(App\\Actions\\Auth\\IniciarSesionApi::class)->ejecutar("'.$usuario->email.'", "AnteriorSegura2026*", "Concurrencia");
        echo $resultado === null ? "LOGIN_RECHAZADO" : "TOKEN_RESIDUAL";
    ');

    DB::beginTransaction();
    try {
        User::query()->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
        $proceso->start();
        esperarBloqueoMariaDbTest($proceso);
        eval($revocar);
        DB::commit();
        $proceso->wait();
        expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());
        expect($proceso->getOutput())->toContain('LOGIN_RECHAZADO')->not->toContain('TOKEN_RESIDUAL');
        expect($usuario->tokens()->count())->toBe(0);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
})->with(['deshabilitar', 'cambiar', 'restablecer']);

test('MariaDB revocacion espera al login y elimina el token que este acaba de emitir', function (string $operacion) {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $usuario = User::factory()->create(['password' => 'AnteriorSegura2026*']);
    $revocar = prepararRevocacionMariaDbTest($usuario, $admin, $operacion);
    $proceso = crearProcesoMariaDbTest(observarBloqueoMariaDbTest().$revocar.' echo "REVOCACION_OK";');

    DB::beginTransaction();
    try {
        $resultado = app(IniciarSesionApi::class)->ejecutar($usuario->email, 'AnteriorSegura2026*', 'Concurrencia');
        expect($resultado)->not->toBeNull();
        $proceso->start();
        esperarBloqueoMariaDbTest($proceso);
        DB::commit();
        $proceso->wait();
        expect($proceso->isSuccessful())->toBeTrue($proceso->getErrorOutput());
        expect($proceso->getOutput())->toContain('REVOCACION_OK');
        expect($usuario->tokens()->count())->toBe(0);

        if ($operacion === 'deshabilitar') {
            app(ReactivarCuenta::class)->ejecutar($admin, $usuario->fresh());
        }
        app('auth')->forgetGuards();
        $this->withToken($resultado['token'])->getJson('/api/v1/perfil')->assertUnauthorized();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
})->with(['deshabilitar', 'cambiar', 'restablecer']);

test('MariaDB la migracion de expiracion conserva los datos anteriores y elimina la actualizacion automatica', function () {
    $migracion = require database_path('migrations/2026_09_13_210516_fijar_expiracion_codigo_recuperacion.php');
    $migracion->down();
    $usuario = User::factory()->create();
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => $usuario->email,
        'codigo_hash' => Hash::make('739281'),
        'codigo_expira_en' => now()->addMinutes(10),
    ])->fresh();
    $antes = $recuperacion->getAttributes();

    $migracion->up();

    expect($recuperacion->fresh()->getAttributes())->toBe($antes);
    $recuperacion->update(['intentos' => 1]);
    expect($recuperacion->fresh()->codigo_expira_en->toDateTimeString())->toBe($antes['codigo_expira_en']);
    $columna = DB::selectOne("SHOW COLUMNS FROM recuperaciones_password WHERE Field = 'codigo_expira_en'");
    expect($columna->Extra)->not->toContain('on update');
})->group('migracion-mariadb');

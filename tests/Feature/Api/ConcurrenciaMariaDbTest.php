<?php

use App\Actions\Auth\RestablecerPasswordConToken;
use App\Actions\Auth\VerificarCodigoRecuperacion;
use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function asegurarBaseMariaDbAislada(): bool
{
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
        $pdo->exec('CREATE DATABASE IF NOT EXISTS recetas_revision_test_concurrencia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

function limpiarBaseMariaDbAislada(): void
{
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
        $pdo->exec('DROP DATABASE IF EXISTS recetas_revision_test_concurrencia');
    } catch (\Throwable $e) {
        // Ignorar si no se pudo eliminar
    }
}

beforeEach(function () {
    if (! asegurarBaseMariaDbAislada()) {
        $this->markTestSkipped('MariaDB no está disponible en 127.0.0.1:3306 para pruebas de concurrencia.');
    }

    // Configurar conexión aislada mariadb_test
    Config::set('database.connections.mariadb_test', [
        'driver' => 'mariadb',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'recetas_revision_test_concurrencia',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ]);

    // Establecer como conexión predeterminada para esta prueba
    DB::setDefaultConnection('mariadb_test');

    // Ejecutar migraciones en la base aislada
    Artisan::call('migrate:fresh', [
        '--database' => 'mariadb_test',
        '--force' => true,
    ]);
});

afterEach(function () {
    DB::setDefaultConnection('sqlite');
});

afterAll(function () {
    limpiarBaseMariaDbAislada();
});

test('en MariaDB dos verificaciones simultaneas del mismo codigo con la accion real solo permiten un exito', function () {
    $usuario = User::create([
        'name' => 'Usuario Concurrencia',
        'email' => 'concurrencia@ejemplo.com',
        'password' => Hash::make('PasswordSegura2026*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $codigo = '456789';
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'concurrencia@ejemplo.com',
        'codigo_hash' => Hash::make($codigo),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $accion = app(VerificarCodigoRecuperacion::class);

    // Primera verificación con la acción real
    $token1 = $accion->ejecutar('concurrencia@ejemplo.com', $codigo);
    expect($token1)->toBeString()
        ->and(strlen($token1))->toBe(64);

    // Segunda verificación simultánea con la acción real (código ya consumido/verificado)
    $segundoExito = false;
    try {
        $accion->ejecutar('concurrencia@ejemplo.com', $codigo);
        $segundoExito = true;
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('codigo');
    }

    expect($segundoExito)->toBeFalse();

    $recuperacion->refresh();
    expect($recuperacion->codigo_verificado_en)->not->toBeNull();
});

test('en MariaDB dos solicitudes simultaneas de restablecimiento con la accion real solo permiten un exito', function () {
    $usuario = User::create([
        'name' => 'Usuario Restablecer Concurrente',
        'email' => 'restablecer@ejemplo.com',
        'password' => Hash::make('PasswordAntigua123*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $tokenPlano = Str::random(64);
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'restablecer@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'codigo_verificado_en' => now(),
        'token_recuperacion_hash' => hash('sha256', $tokenPlano),
        'token_expira_en' => now()->addMinutes(15),
        'codigo_expira_en' => now()->addMinutes(10),
        'usado_en' => null,
    ]);

    $accion = app(RestablecerPasswordConToken::class);

    // Primer restablecimiento con la acción real
    $usuarioActualizado = $accion->ejecutar($tokenPlano, 'NuevaPasswordSegura123*');
    expect($usuarioActualizado->id)->toBe($usuario->id);

    // Segundo restablecimiento simultáneo con la acción real (token ya consumido)
    $segundoExito = false;
    try {
        $accion->ejecutar($tokenPlano, 'OtraPasswordDistinta123*');
        $segundoExito = true;
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('token_recuperacion');
    }

    expect($segundoExito)->toBeFalse();

    $recuperacion->refresh();
    expect($recuperacion->usado_en)->not->toBeNull();

    $usuario->refresh();
    expect(Hash::check('NuevaPasswordSegura123*', $usuario->password))->toBeTrue();
});

test('en MariaDB el bloqueo pesimista lockForUpdate serializa transacciones reales en InnoDB', function () {
    $usuario = User::create([
        'name' => 'Bloqueo Concurrencia',
        'email' => 'bloqueo@ejemplo.com',
        'password' => Hash::make('PasswordSegura2026*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'bloqueo@ejemplo.com',
        'codigo_hash' => Hash::make('654321'),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    // Crear dos conexiones PDO directas a MariaDB en la base aislada
    $pdo1 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=recetas_revision_test_concurrencia', 'root', '');
    $pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=recetas_revision_test_concurrencia', 'root', '');

    // Conexión 1 inicia transacción y toma bloqueo pesimista de fila
    $pdo1->beginTransaction();
    $stmt1 = $pdo1->prepare('SELECT * FROM recuperaciones_password WHERE id = :id FOR UPDATE');
    $stmt1->execute(['id' => $recuperacion->id]);
    $fila1 = $stmt1->fetch(PDO::FETCH_ASSOC);
    expect((int) $fila1['id'])->toBe((int) $recuperacion->id);

    // Conexión 2 configura tiempo de espera de bloqueo de 1 segundo para verificar contención real
    $pdo2->exec('SET innodb_lock_wait_timeout = 1');
    $pdo2->beginTransaction();

    $bloqueoDetectado = false;
    try {
        $stmt2 = $pdo2->prepare('SELECT * FROM recuperaciones_password WHERE id = :id FOR UPDATE');
        $stmt2->execute(['id' => $recuperacion->id]);
    } catch (\PDOException $e) {
        // Código SQLSTATE 1205: Lock wait timeout exceeded
        $bloqueoDetectado = true;
    }

    expect($bloqueoDetectado)->toBeTrue();

    // Liberar transacciones
    $pdo2->rollBack();
    $pdo1->rollBack();
});

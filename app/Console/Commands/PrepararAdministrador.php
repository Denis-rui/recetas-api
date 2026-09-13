<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PrepararAdministrador extends Command
{
    protected $signature = 'app:crear-primer-administrador';

    protected $description = 'Crea el administrador inicial con una contraseña oculta, sin modificar cuentas existentes';

    public function handle(): int
    {
        if (User::query()->where('rol', 'administrador')->exists()) {
            $this->error('Ya existe un administrador. Gestione las cuentas desde el panel.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Ejecute este comando en una terminal interactiva para introducir la contraseña de forma segura.');

            return self::FAILURE;
        }

        $datos = [
            'name' => trim((string) $this->ask('Nombre completo')),
            'email' => strtolower(trim((string) $this->ask('Correo electrónico'))),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)', false),
            'password_confirmation' => $this->secret('Confirmar contraseña', false),
        ];
        $validador = Validator::make($datos, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $mensaje) {
                $this->error($mensaje);
            }

            return self::FAILURE;
        }

        try {
            $creado = DB::transaction(function () use ($datos): bool {
                if (User::query()->where('rol', 'administrador')->lockForUpdate()->first()) {
                    return false;
                }

                User::query()->create([
                    'name' => $datos['name'],
                    'email' => $datos['email'],
                    'password' => $datos['password'],
                    'rol' => 'administrador',
                    'activo' => true,
                ]);

                return true;
            });
        } catch (QueryException $exception) {
            report($exception);
            $this->error('No se pudo crear la cuenta. Compruebe el estado de la base y las cuentas existentes.');

            return self::FAILURE;
        }

        if (! $creado) {
            $this->error('Ya existe un administrador. Gestione las cuentas desde el panel.');

            return self::FAILURE;
        }

        $this->info('Administrador inicial creado. Inicie sesión con las credenciales indicadas.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ActualizarPerfil
{
    /**
     * Actualiza los datos personales del propio administrador.
     * Garantiza que rol y estado permanezcan inalterables (RF-10, RN-07).
     *
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(User $usuario, array $datos, ?UploadedFile $foto = null): User
    {
        $fotoAnterior = $usuario->foto_perfil;
        $nuevaFoto = null;

        if ($foto !== null) {
            try {
                $nuevaFoto = $foto->store('perfiles', 'public');
            } catch (\Throwable $e) {
                Log::error('Fallo al almacenar la nueva fotografía de perfil: '.$e->getMessage());

                throw ValidationException::withMessages([
                    'foto_perfil' => 'No fue posible guardar la fotografía de perfil. Por favor, inténtelo de nuevo.',
                ]);
            }

            if (! $nuevaFoto || ! Storage::disk('public')->exists($nuevaFoto)) {
                if ($nuevaFoto && Storage::disk('public')->exists($nuevaFoto)) {
                    Storage::disk('public')->delete($nuevaFoto);
                }

                Log::error('El almacenamiento de la nueva fotografía de perfil no devolvió una ruta válida.');

                throw ValidationException::withMessages([
                    'foto_perfil' => 'No fue posible guardar la fotografía de perfil. Por favor, inténtelo de nuevo.',
                ]);
            }
        }

        try {
            DB::transaction(function () use ($usuario, $datos, $nuevaFoto) {
                // Bloqueo pesimista del usuario primero (orden canónico)
                $userRecord = User::where('id', $usuario->id)->lockForUpdate()->first();
                if (! $userRecord) {
                    return;
                }

                $cambioEmail = false;
                $emailAnterior = $userRecord->email;

                if (array_key_exists('name', $datos)) {
                    $userRecord->name = $datos['name'];
                    $usuario->name = $datos['name'];
                }

                if (array_key_exists('email', $datos) && strtolower((string) $datos['email']) !== strtolower((string) $emailAnterior)) {
                    $userRecord->email = $datos['email'];
                    $userRecord->email_verified_at = null;
                    $usuario->email = $datos['email'];
                    $usuario->email_verified_at = null;
                    $cambioEmail = true;
                }

                if ($nuevaFoto !== null) {
                    $userRecord->foto_perfil = $nuevaFoto;
                    $usuario->foto_perfil = $nuevaFoto;
                }

                $userRecord->save();

                if ($cambioEmail) {
                    DB::table('recuperaciones_password')
                        ->where(function ($query) use ($userRecord, $emailAnterior) {
                            $query->where('user_id', $userRecord->id)
                                ->orWhere('email', $emailAnterior);
                        })
                        ->whereNull('invalidado_en')
                        ->update(['invalidado_en' => now()]);
                }
            });
        } catch (\Throwable $e) {
            if ($nuevaFoto !== null && Storage::disk('public')->exists($nuevaFoto)) {
                Storage::disk('public')->delete($nuevaFoto);
            }

            $usuario->refresh();

            Log::error('Fallo al actualizar el perfil en la base de datos: '.$e->getMessage());

            throw ValidationException::withMessages([
                'foto_perfil' => 'No fue posible actualizar los datos de su perfil. Por favor, inténtelo de nuevo.',
            ]);
        }

        if ($nuevaFoto !== null && $fotoAnterior && $fotoAnterior !== $nuevaFoto) {
            try {
                if (Storage::disk('public')->exists($fotoAnterior)) {
                    $eliminado = Storage::disk('public')->delete($fotoAnterior);
                    if (! $eliminado) {
                        Log::warning("No se pudo eliminar la fotografía anterior durante la limpieza: {$fotoAnterior}");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Error al eliminar la fotografía anterior durante la limpieza ({$fotoAnterior}): {$e->getMessage()}");
            }
        }

        return $usuario;
    }
}

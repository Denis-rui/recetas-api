<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Usuarios\ActualizarPerfil;
use App\Actions\Usuarios\CambiarPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Perfil\ActualizarFotoPerfilApiRequest;
use App\Http\Requests\Api\V1\Perfil\ActualizarPerfilApiRequest;
use App\Http\Requests\Api\V1\Perfil\CambiarPasswordApiRequest;
use App\Http\Resources\Api\V1\PerfilResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PerfilController extends Controller
{
    /**
     * Devuelve los datos de la cuenta autenticada (200 OK).
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new PerfilResource($request->user()),
        ], Response::HTTP_OK);
    }

    /**
     * Actualiza parcialmente el nombre o el correo de la propia cuenta (200 OK).
     */
    public function update(ActualizarPerfilApiRequest $request, ActualizarPerfil $accion): JsonResponse
    {
        $datos = $request->safe()->only(['name', 'email']);

        $usuario = $accion->ejecutar(
            $request->user(),
            $datos
        );

        return response()->json([
            'mensaje' => 'Perfil actualizado exitosamente.',
            'usuario' => new PerfilResource($usuario),
        ], Response::HTTP_OK);
    }

    /**
     * Sirve de forma autenticada la fotografía de perfil de la cuenta activa.
     */
    public function foto(Request $request): BinaryFileResponse
    {
        $usuario = $request->user();

        if (! $usuario->foto_perfil || ! Storage::disk('public')->exists($usuario->foto_perfil)) {
            abort(404, 'Fotografía de perfil no encontrada.');
        }

        $rutaAbsoluta = Storage::disk('public')->path($usuario->foto_perfil);
        $mime = Storage::disk('public')->mimeType($usuario->foto_perfil) ?? 'image/jpeg';

        return response()->file($rutaAbsoluta, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }

    /**
     * Actualiza la fotografía de perfil de la cuenta autenticada (200 OK).
     */
    public function actualizarFoto(ActualizarFotoPerfilApiRequest $request, ActualizarPerfil $accion): JsonResponse
    {
        $usuario = $accion->ejecutar(
            $request->user(),
            [],
            $request->file('foto_perfil')
        );

        return response()->json([
            'mensaje' => 'Fotografía de perfil actualizada exitosamente.',
            'foto_perfil_url' => $usuario->foto_perfil_url,
        ], Response::HTTP_OK);
    }

    /**
     * Modifica la contraseña del usuario y revoca de inmediato todas las sesiones, tokens y accesos persistentes (200 OK).
     */
    public function cambiarPassword(CambiarPasswordApiRequest $request, CambiarPassword $accion): JsonResponse
    {
        $accion->ejecutar(
            $request->user(),
            (string) $request->validated('password')
        );

        return response()->json([
            'mensaje' => 'Contraseña actualizada exitosamente. Todas las sesiones y tokens han sido revocados. Debe iniciar sesión nuevamente.',
        ], Response::HTTP_OK);
    }
}

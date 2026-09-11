<?php

namespace App\Http\Controllers\Web;

use App\Actions\Usuarios\ActualizarPerfil;
use App\Actions\Usuarios\CambiarPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Perfil\ActualizarPerfilRequest;
use App\Http\Requests\Perfil\CambiarPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PerfilController extends Controller
{
    /**
     * Muestra la vista de Mi Perfil (RF-10, RF-11, pantalla 7.3).
     */
    public function edit(Request $request): View
    {
        $usuario = $request->user();

        return view('perfil.edit', compact('usuario'));
    }

    /**
     * Actualiza los datos personales del administrador autenticado (200 OK si es JSON, 302 si es Web).
     */
    public function update(ActualizarPerfilRequest $request, ActualizarPerfil $accion): RedirectResponse|JsonResponse
    {
        $usuario = $accion->ejecutar(
            $request->user(),
            $request->validated(),
            $request->file('foto_perfil')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => 'Sus datos personales han sido actualizados exitosamente.',
                'usuario' => $usuario,
            ], Response::HTTP_OK);
        }

        return redirect()->route('perfil.edit')
            ->with('exito_perfil', 'Sus datos personales han sido actualizados exitosamente.');
    }

    /**
     * Actualiza la contraseña del administrador autenticado (200 OK si es JSON, 302 si es Web).
     */
    public function cambiarPassword(CambiarPasswordRequest $request, CambiarPassword $accion): RedirectResponse|JsonResponse
    {
        $accion->ejecutar(
            $request->user(),
            $request->validated('password')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => 'Su contraseña ha sido cambiada exitosamente.',
            ], Response::HTTP_OK);
        }

        return redirect()->route('perfil.edit')
            ->with('exito_password', 'Su contraseña ha sido cambiada exitosamente.');
    }
}

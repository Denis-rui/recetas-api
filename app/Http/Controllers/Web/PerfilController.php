<?php

namespace App\Http\Controllers\Web;

use App\Actions\Usuarios\ActualizarPerfil;
use App\Actions\Usuarios\CambiarPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Perfil\ActualizarPerfilRequest;
use App\Http\Requests\Perfil\CambiarPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
     * Actualiza los datos personales del administrador autenticado.
     */
    public function update(ActualizarPerfilRequest $request, ActualizarPerfil $accion): RedirectResponse
    {
        $accion->ejecutar(
            $request->user(),
            $request->validated(),
            $request->file('foto_perfil')
        );

        return redirect()->route('perfil.edit')
            ->with('exito_perfil', 'Sus datos personales han sido actualizados exitosamente.');
    }

    /**
     * Actualiza la contraseña del administrador autenticado.
     */
    public function cambiarPassword(CambiarPasswordRequest $request, CambiarPassword $accion): RedirectResponse
    {
        $accion->ejecutar(
            $request->user(),
            $request->validated('password')
        );

        return redirect()->route('perfil.edit')
            ->with('exito_password', 'Su contraseña ha sido cambiada exitosamente.');
    }
}

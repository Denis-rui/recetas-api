<?php

namespace App\Http\Controllers\Web;

use App\Actions\Usuarios\ActualizarCuenta;
use App\Actions\Usuarios\CambiarRol;
use App\Actions\Usuarios\ConsultarCuentasDataTable;
use App\Actions\Usuarios\CrearCuenta;
use App\Actions\Usuarios\DeshabilitarCuenta;
use App\Actions\Usuarios\ReactivarCuenta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\ActualizarUsuarioRequest;
use App\Http\Requests\Usuarios\CrearUsuarioRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UsuarioController extends Controller
{
    /**
     * Muestra la tabla de cuentas o devuelve los datos asíncronos para DataTables Server-Side.
     */
    public function index(Request $request, ConsultarCuentasDataTable $accion): View|JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($accion->ejecutar($request, $request->user()));
        }

        return view('usuarios.index');
    }

    /**
     * Muestra el formulario para crear una nueva cuenta.
     */
    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('usuarios.create');
    }

    /**
     * Guarda la nueva cuenta en la base de datos (201 Created si es JSON, 302 si es Web).
     */
    public function store(CrearUsuarioRequest $request, CrearCuenta $accion): RedirectResponse|JsonResponse
    {
        $usuario = $accion->ejecutar(
            $request->validated(),
            $request->file('foto_perfil')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => "La cuenta de {$usuario->name} fue creada exitosamente.",
                'usuario' => $usuario,
            ], Response::HTTP_CREATED);
        }

        return redirect()->route('usuarios.index')
            ->with('exito', "La cuenta de {$usuario->name} fue creada exitosamente.");
    }

    /**
     * Muestra el formulario para editar una cuenta existente.
     */
    public function edit(User $usuario): View
    {
        Gate::authorize('update', $usuario);

        return view('usuarios.edit', compact('usuario'));
    }

    /**
     * Actualiza los datos de la cuenta especificada (200 OK si es JSON, 302 si es Web).
     */
    public function update(ActualizarUsuarioRequest $request, User $usuario, ActualizarCuenta $accion): RedirectResponse|JsonResponse
    {
        $usuarioActualizado = $accion->ejecutar(
            $usuario,
            $request->validated(),
            $request->file('foto_perfil')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => 'Los datos de la cuenta fueron actualizados correctamente.',
                'usuario' => $usuarioActualizado,
            ], Response::HTTP_OK);
        }

        return redirect()->route('usuarios.index')
            ->with('exito', 'Los datos de la cuenta fueron actualizados correctamente.');
    }

    /**
     * Cambia el rol de una cuenta (RF-07, RN-06, RN-07, RN-10).
     */
    public function cambiarRol(Request $request, User $usuario, CambiarRol $accion): RedirectResponse|JsonResponse
    {
        $request->validate([
            'rol' => ['required', 'in:administrador,usuario'],
        ], [
            'rol.required' => 'Debe indicar el nuevo rol.',
            'rol.in' => 'El rol seleccionado no es válido.',
        ]);

        $accion->ejecutar($request->user(), $usuario, $request->input('rol'));

        $nombreRol = $usuario->rol === 'administrador' ? 'Administrador' : 'Usuario normal';
        $mensaje = "El rol de {$usuario->name} ha sido modificado a {$nombreRol}.";

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'usuario' => $usuario,
            ], Response::HTTP_OK);
        }

        return redirect()->route('usuarios.index')
            ->with('exito', $mensaje);
    }

    /**
     * Deshabilita una cuenta (RF-08, RN-06, RN-07, RN-08).
     */
    public function deshabilitar(Request $request, User $usuario, DeshabilitarCuenta $accion): RedirectResponse|JsonResponse
    {
        $accion->ejecutar($request->user(), $usuario);

        $mensaje = "La cuenta de {$usuario->name} ha sido deshabilitada.";

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'usuario' => $usuario,
            ], Response::HTTP_OK);
        }

        return redirect()->route('usuarios.index')
            ->with('exito', $mensaje);
    }

    /**
     * Reactiva una cuenta deshabilitada (RF-09, RN-09).
     */
    public function reactivar(Request $request, User $usuario, ReactivarCuenta $accion): RedirectResponse|JsonResponse
    {
        $accion->ejecutar($request->user(), $usuario);

        $mensaje = "La cuenta de {$usuario->name} ha sido reactivada.";

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'usuario' => $usuario,
            ], Response::HTTP_OK);
        }

        return redirect()->route('usuarios.index')
            ->with('exito', $mensaje);
    }
}

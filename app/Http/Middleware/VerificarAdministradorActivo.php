<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarAdministradorActivo
{
    /**
     * Comprueba que el usuario autenticado esté activo y posea el rol de administrador.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $usuario = Auth::user();

        if (! $usuario->estaActivo()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Su cuenta ha sido deshabilitada. Comuníquese con un administrador.',
            ]);
        }

        if (! $usuario->esAdministrador()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Acceso denegado. Esta plataforma es exclusiva para administradores.',
            ]);
        }

        return $next($request);
    }
}


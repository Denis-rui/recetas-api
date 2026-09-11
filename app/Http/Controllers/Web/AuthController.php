<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Muestra la pantalla de inicio de sesión.
     */
    public function mostrarLogin(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->esAdministrador() && Auth::user()->estaActivo()) {
            return redirect()->route('usuarios.index');
        }

        return view('auth.login');
    }

    /**
     * Procesa la solicitud de inicio de sesión verificando credenciales, estado y rol.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $credenciales = $request->only('email', 'password');
        $recordar = $request->boolean('remember');

        if (! Auth::attempt($credenciales, $recordar)) {
            RateLimiter::hit($request->throttleKey());

            return back()->withErrors([
                'email' => 'Las credenciales ingresadas no coinciden con nuestros registros.',
            ])->onlyInput('email');
        }

        $usuario = Auth::user();

        // Regla RF-01 y RN-01: La cuenta debe estar activa
        if (! $usuario->estaActivo()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Su cuenta se encuentra deshabilitada. Comuníquese con la administración.',
            ])->onlyInput('email');
        }

        // Regla RF-01 y RN-01: Solo administradores pueden ingresar a la web
        if (! $usuario->esAdministrador()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Acceso denegado. Esta plataforma está reservada exclusivamente para administradores.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        return redirect()->intended(route('usuarios.index'));
    }

    /**
     * Cierra la sesión activa del administrador.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('exito', 'Ha cerrado sesión correctamente.');
    }
}


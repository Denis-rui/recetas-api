<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarCuentaActivaApi
{
    /**
     * Comprueba que la cuenta autenticada en la API continúe activa.
     * Si está deshabilitada, revoca sus tokens residuales y responde HTTP 403 Forbidden.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json([
                'mensaje' => 'No autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $usuario->estaActivo()) {
            if (method_exists($usuario, 'tokens')) {
                $usuario->tokens()->delete();
            }

            return response()->json([
                'mensaje' => 'Su cuenta se encuentra deshabilitada. Comuníquese con la administración.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}


<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RestablecerPasswordConToken;
use App\Actions\Auth\SolicitarRecuperacionPassword;
use App\Actions\Auth\VerificarCodigoRecuperacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RestablecerPasswordRequest;
use App\Http\Requests\Api\V1\Auth\SolicitarRecuperacionRequest;
use App\Http\Requests\Api\V1\Auth\VerificarCodigoRecuperacionRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RecuperacionPasswordController extends Controller
{
    /**
     * Paso 1: Solicita un código de recuperación por correo electrónico (200 OK).
     */
    public function solicitar(
        SolicitarRecuperacionRequest $request,
        SolicitarRecuperacionPassword $accion
    ): JsonResponse {
        $mensaje = $accion->ejecutar((string) $request->validated('email'));

        return response()->json([
            'mensaje' => $mensaje,
        ], Response::HTTP_OK);
    }

    /**
     * Paso 2: Verifica el código numérico de 6 dígitos y emite un token_recuperacion temporal (200 OK).
     */
    public function verificar(
        VerificarCodigoRecuperacionRequest $request,
        VerificarCodigoRecuperacion $accion
    ): JsonResponse {
        $tokenRecuperacion = $accion->ejecutar(
            (string) $request->validated('email'),
            (string) $request->validated('codigo')
        );

        return response()->json([
            'mensaje' => 'Código verificado exitosamente.',
            'token_recuperacion' => $tokenRecuperacion,
        ], Response::HTTP_OK);
    }

    /**
     * Paso 3: Restablece la contraseña utilizando el token_recuperacion de un solo uso (200 OK).
     */
    public function restablecer(
        RestablecerPasswordRequest $request,
        RestablecerPasswordConToken $accion
    ): JsonResponse {
        $accion->ejecutar(
            (string) $request->validated('token_recuperacion'),
            (string) $request->validated('password')
        );

        return response()->json([
            'mensaje' => 'Contraseña restablecida exitosamente. Ya puede iniciar sesión con su nueva contraseña.',
        ], Response::HTTP_OK);
    }
}


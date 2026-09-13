<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RegistrarUsuarioApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginApiRequest;
use App\Http\Requests\Api\V1\Auth\RegistroApiRequest;
use App\Http\Resources\Api\V1\PerfilResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario normal y devuelve el recurso de la cuenta creada (201 Created).
     */
    public function registro(RegistroApiRequest $request, RegistrarUsuarioApi $accion): JsonResponse
    {
        $usuario = $accion->ejecutar($request->validated());

        return response()->json([
            'mensaje' => 'Cuenta registrada exitosamente.',
            'usuario' => new PerfilResource($usuario),
        ], Response::HTTP_CREATED);
    }

    /**
     * Inicia sesión para usuarios normales y administradores activos, devolviendo el Bearer token (200 OK).
     */
    public function login(LoginApiRequest $request): JsonResponse
    {
        $emailNormalizado = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $usuario = User::where('email', $emailNormalizado)->first();

        // Rechazo genérico tanto si no existe, si la contraseña es errónea o si la cuenta está deshabilitada
        if (! $usuario || ! Hash::check($password, $usuario->password) || ! $usuario->estaActivo()) {
            return response()->json([
                'mensaje' => 'Las credenciales proporcionadas son incorrectas o la cuenta se encuentra inactiva.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $dispositivo = $request->filled('dispositivo')
            ? (string) $request->input('dispositivo')
            : 'Dispositivo móvil';

        $token = $usuario->createToken($dispositivo)->plainTextToken;

        return response()->json([
            'mensaje' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'token_type' => 'Bearer',
            'usuario' => new PerfilResource($usuario),
        ], Response::HTTP_OK);
    }

    /**
     * Cierra la sesión activa revocando únicamente el token utilizado en esta petición (200 OK).
     */
    public function logout(Request $request): JsonResponse
    {
        $tokenActual = $request->user()?->currentAccessToken();

        if ($tokenActual) {
            $tokenActual->delete();
        }

        return response()->json([
            'mensaje' => 'Sesión cerrada correctamente.',
        ], Response::HTTP_OK);
    }
}

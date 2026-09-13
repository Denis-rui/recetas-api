<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Valoraciones\RegistrarValoracion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Valoraciones\GuardarValoracionRequest;
use App\Http\Resources\Api\V1\MiValoracionResource;
use App\Models\Receta;
use App\Models\Valoracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValoracionController extends Controller
{
    /**
     * Consulta la puntuación propia que el usuario autenticado otorgó a una receta.
     */
    public function show(Request $request, mixed $receta): MiValoracionResource
    {
        if (! is_numeric($receta) || (int) $receta <= 0) {
            abort(404, 'Receta no encontrada.');
        }

        $id = (int) $receta;

        $recetaModel = Receta::query()
            ->whereNotNull('publicada_en')
            ->find($id);

        if (! $recetaModel) {
            abort(404, 'Receta no encontrada.');
        }

        $usuario = $request->user();

        $valoracion = Valoracion::where('usuario_id', $usuario->id)
            ->where('receta_id', $recetaModel->id)
            ->first();

        $promedio = $recetaModel->valoraciones()->avg('puntuacion');
        $cantidad = (int) $recetaModel->valoraciones()->count();

        return new MiValoracionResource([
            'receta_id' => $recetaModel->id,
            'puntuacion' => $valoracion?->puntuacion,
            'valoracion_promedio' => $promedio !== null ? round((float) $promedio, 2) : null,
            'cantidad_valoraciones' => $cantidad,
        ]);
    }

    /**
     * Registra o actualiza la puntuación de 1 a 5 asignada por el usuario a la receta.
     */
    public function store(GuardarValoracionRequest $request, mixed $receta, RegistrarValoracion $accion): JsonResponse
    {
        $puntuacion = (int) $request->validated('puntuacion');
        $resultado = $accion->ejecutar($request->user(), $receta, $puntuacion);

        return response()->json([
            'mensaje' => 'Valoración registrada exitosamente.',
            'data' => new MiValoracionResource([
                'receta_id' => (int) $receta,
                ...$resultado,
            ]),
        ], Response::HTTP_OK);
    }
}


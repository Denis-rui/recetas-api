<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recetas\CancelarSolicitudRevision;
use App\Actions\Recetas\ImagenRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MisSolicitudes\ListarMisSolicitudesRequest;
use App\Http\Resources\Api\V1\MiSolicitudCatalogoResource;
use App\Http\Resources\Api\V1\MiSolicitudDetalleResource;
use App\Models\SolicitudRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MisSolicitudesController extends Controller
{
    /**
     * Listado paginado de solicitudes de revisión del autor autenticado.
     */
    public function index(ListarMisSolicitudesRequest $request): AnonymousResourceCollection
    {
        $usuario = $request->user();

        $query = SolicitudRevision::query()
            ->where('solicitado_por', $usuario->id)
            ->with('receta');

        $estado = $request->validated('estado', 'todos');
        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        $tipo = $request->validated('tipo', 'todos');
        if ($tipo !== 'todos') {
            $query->where('tipo', $tipo);
        }

        $perPage = (int) $request->input('per_page', 15);

        $solicitudes = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return MiSolicitudCatalogoResource::collection($solicitudes);
    }

    /**
     * Consulta el detalle de una solicitud propia con su propuesta resuelta.
     */
    public function show(Request $request, mixed $solicitud): MiSolicitudDetalleResource
    {
        $solicitudModel = $this->obtenerSolicitudAutorizada($request, $solicitud);
        $solicitudModel->loadMissing('receta');

        return new MiSolicitudDetalleResource($solicitudModel);
    }

    /**
     * Cancela una solicitud propia en estado pendiente.
     */
    public function cancelar(Request $request, mixed $solicitud, CancelarSolicitudRevision $accion): JsonResponse
    {
        $solicitudModel = $this->obtenerSolicitudAutorizada($request, $solicitud);

        $cancelada = $accion->ejecutar($request->user(), $solicitudModel);

        return response()->json([
            'mensaje' => 'Solicitud cancelada exitosamente.',
            'solicitud' => new MiSolicitudCatalogoResource($cancelada),
        ], Response::HTTP_OK);
    }

    /**
     * Sirve la imagen asociada a la propuesta de revisión de una solicitud propia.
     */
    public function imagen(Request $request, mixed $solicitud, ImagenRevision $imagenes): BinaryFileResponse
    {
        $solicitudModel = $this->obtenerSolicitudAutorizada($request, $solicitud);

        $rutaImagen = data_get($solicitudModel->contenido, 'imagen');
        if (! $rutaImagen) {
            abort(404, 'Imagen no encontrada.');
        }

        $imagen = $imagenes->localizar($rutaImagen, $solicitudModel->receta_id);
        if (! $imagen) {
            abort(404, 'Imagen no encontrada en el almacenamiento.');
        }

        return response()->file($imagen['ruta'], [
            'Content-Type' => $imagen['mime'],
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }

    /**
     * Busca la solicitud y comprueba que pertenezca al usuario autenticado.
     */
    private function obtenerSolicitudAutorizada(Request $request, mixed $solicitud): SolicitudRevision
    {
        if (! is_numeric($solicitud) || (int) $solicitud <= 0) {
            abort(404, 'Solicitud no encontrada.');
        }

        $id = (int) $solicitud;
        $solicitudModel = SolicitudRevision::find($id);

        if (! $solicitudModel) {
            abort(404, 'Solicitud no encontrada.');
        }

        if ($solicitudModel->solicitado_por !== $request->user()->id) {
            abort(403, 'No tiene permiso para acceder a esta solicitud.');
        }

        return $solicitudModel;
    }
}

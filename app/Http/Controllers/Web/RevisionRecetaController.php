<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recetas\ImagenRevision;
use App\Actions\Recetas\PrepararDetalleRevision;
use App\Actions\Recetas\ProcesarRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Revision\DecidirRevisionRequest;
use App\Models\SolicitudRevision;
use App\Rules\IdentificadorEntero;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RevisionRecetaController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', SolicitudRevision::class);
        try {
            $filtros = $request->validate([
                'page' => ['sometimes', 'bail', new IdentificadorEntero, 'integer', 'max:'.intdiv(PHP_INT_MAX, 15)],
                'estado' => ['sometimes', Rule::in(['pendiente', 'aprobada', 'rechazada', 'cancelada', 'todos'])],
                'tipo' => ['sometimes', Rule::in(['publicacion', 'correccion', 'todos'])],
            ], ['in' => 'El filtro :attribute no es válido.']);
        } catch (ValidationException $exception) {
            return redirect()->route('revision-recetas.index')->withErrors($exception->errors());
        }
        $estado = $filtros['estado'] ?? 'pendiente';
        $tipo = $filtros['tipo'] ?? 'todos';
        $solicitudes = SolicitudRevision::query()
            ->whereHas('receta', fn ($consulta) => $consulta->whereColumn('recetas.creado_por', 'solicitudes_revision.solicitado_por'))
            ->with('solicitante:id,name')
            ->when($estado !== 'todos', fn ($consulta) => $consulta->where('estado', $estado))
            ->when($tipo !== 'todos', fn ($consulta) => $consulta->where('tipo', $tipo))
            ->orderBy('created_at')->orderBy('id')->paginate(15)->withQueryString();

        return response()->view('revision-recetas.index', compact('solicitudes', 'estado', 'tipo'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(SolicitudRevision $solicitud, PrepararDetalleRevision $detalle): Response
    {
        Gate::authorize('view', $solicitud);

        return response()->view('revision-recetas.show', $detalle->ejecutar($solicitud))
            ->header('Cache-Control', 'private, no-store');
    }

    public function aprobar(DecidirRevisionRequest $request, SolicitudRevision $solicitud, ProcesarRevision $accion): RedirectResponse
    {
        return $this->decidir($request, $solicitud, $accion, 'aprobar');
    }

    public function rechazar(DecidirRevisionRequest $request, SolicitudRevision $solicitud, ProcesarRevision $accion): RedirectResponse
    {
        return $this->decidir($request, $solicitud, $accion, 'rechazar');
    }

    private function decidir(DecidirRevisionRequest $request, SolicitudRevision $solicitud, ProcesarRevision $accion, string $decision): RedirectResponse
    {
        $destino = redirect()->route('revision-recetas.show', $solicitud);
        try {
            $accion->ejecutar($request->user(), $solicitud, $decision, $request->validated('motivo_rechazo'), $request->boolean('confirmar_correccion_menor'));
        } catch (ValidationException $exception) {
            return $destino->withErrors($exception->errors())->withInput($request->only('motivo_rechazo'));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return $destino->with('error', 'No se pudo guardar la decisión. No se aplicaron cambios parciales. Revisa el estado actualizado antes de intentarlo otra vez.');
        }

        return $destino->with('exito', $decision === 'aprobar' ? 'Solicitud aprobada. La receta publicada fue actualizada.' : 'Solicitud rechazada. El contenido vigente se conserva.');
    }

    public function imagen(SolicitudRevision $solicitud, string $version, ImagenRevision $imagenes): BinaryFileResponse
    {
        Gate::authorize('view', $solicitud);
        if ($version === 'publicada') {
            abort_unless($solicitud->tipo === 'correccion' && $solicitud->receta->publicada_en !== null, 404);
            $ruta = $solicitud->receta->imagen;
        } else {
            abort_unless($version === 'propuesta', 404);
            $ruta = data_get($solicitud->contenido, 'imagen');
        }
        $imagen = $imagenes->localizar($ruta, $solicitud->receta_id);
        abort_unless($imagen, 404);

        return response()->file($imagen['ruta'], [
            'Content-Type' => $imagen['mime'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }
}

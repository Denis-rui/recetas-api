<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('listado-usuarios', function (Request $request) {
            // Si no es petición asíncrona (por ejemplo la carga inicial HTML de la vista), no limitar
            if (! ($request->ajax() || $request->wantsJson())) {
                return Limit::none();
            }

            $limite = (int) config('usuarios.rate_limit', 60);
            $usuarioId = $request->user()?->id;
            // Partición estricta por ID del administrador para evitar cuotas compartidas por IP
            $llave = $usuarioId ? 'admin_'.$usuarioId : 'ip_'.$request->ip();

            return Limit::perMinute($limite)
                ->by($llave)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Has superado el límite de consultas permitidas. Por favor, espera antes de reintentar.',
                        'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });
    }
}

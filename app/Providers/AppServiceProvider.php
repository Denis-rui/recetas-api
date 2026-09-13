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
        $respuestaLimite = function (Request $request, array $headers) {
            return response()->json([
                'mensaje' => 'Demasiadas solicitudes. Espere antes de reintentar.',
                'retry_after' => (int) ($headers['Retry-After'] ?? 60),
            ], 429, $headers);
        };

        RateLimiter::for('api-registro', fn (Request $request) => [
            Limit::perMinute(max(1, (int) config('api.registro_por_minuto', 5)))
                ->by('registro_minuto:'.$request->ip())->response($respuestaLimite),
            Limit::perHour(max(1, (int) config('api.registro_por_hora', 20)))
                ->by('registro_hora:'.$request->ip())->response($respuestaLimite),
        ]);

        RateLimiter::for('api-recuperacion-restablecer', fn (Request $request) => Limit::perMinute(max(1, (int) config('api.restablecer_por_minuto', 10)))
            ->by('restablecer:'.$request->ip())->response($respuestaLimite));

        RateLimiter::for('api-disponibilidad', fn (Request $request) => Limit::perMinute(max(1, (int) config('api.disponibilidad_por_minuto', 30)))
            ->by('disponibilidad:'.$request->ip())->response($respuestaLimite));

        RateLimiter::for('api-escrituras', function (Request $request) use ($respuestaLimite) {
            if ($request->isMethodSafe() || $request->routeIs('api.v1.auth.logout')) {
                return Limit::none();
            }

            return Limit::perMinute(max(1, (int) config('api.escrituras_por_minuto', 60)))
                ->by('escrituras:'.($request->user()?->id ?? $request->ip()))
                ->response($respuestaLimite);
        });

        RateLimiter::for('api-imagenes', function (Request $request) use ($respuestaLimite) {
            if (! $request->hasFile('imagen') && ! $request->hasFile('foto_perfil')) {
                return Limit::none();
            }

            return Limit::perMinute(max(1, (int) config('api.imagenes_por_minuto', 10)))
                ->by('imagenes:'.($request->user()?->id ?? $request->ip()))->response($respuestaLimite);
        });

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

        RateLimiter::for('api-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(5)
                    ->by('login_account:'.$email)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'mensaje' => 'Demasiados intentos de acceso fallidos. Por favor, espere antes de reintentar.',
                            'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                        ], 429, $headers);
                    }),
                Limit::perMinute(10)
                    ->by('login_ip:'.$ip)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'mensaje' => 'Demasiados intentos de acceso desde esta dirección IP. Por favor, espere antes de reintentar.',
                            'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('api-recuperacion-solicitar', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));
            $ip = (string) $request->ip();

            return [
                Limit::perMinutes(1, 1)
                    ->by('recup_cooldown:'.$email)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'mensaje' => 'Debe esperar al menos 60 segundos antes de solicitar un nuevo código de recuperación.',
                            'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                        ], 429, $headers);
                    }),
                Limit::perHour(5)
                    ->by('recup_email:'.$email)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'mensaje' => 'Ha excedido el límite de solicitudes de recuperación para este correo. Por favor, intente más tarde.',
                            'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                        ], 429, $headers);
                    }),
                Limit::perHour(15)
                    ->by('recup_ip:'.$ip)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'mensaje' => 'Ha excedido el límite de solicitudes de recuperación desde esta dirección IP. Por favor, intente más tarde.',
                            'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('api-recuperacion-verificar', function (Request $request) {
            $ip = (string) $request->ip();

            return Limit::perMinute(10)
                ->by('recup_verificar_ip:'.$ip)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'mensaje' => 'Demasiados intentos de verificación. Por favor, espere antes de reintentar.',
                        'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });
    }
}

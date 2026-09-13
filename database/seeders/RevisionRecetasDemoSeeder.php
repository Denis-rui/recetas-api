<?php

namespace Database\Seeders;

use App\Actions\Recetas\ProcesarRevision;
use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RevisionRecetasDemoSeeder extends Seeder
{
    private const PASSWORD = 'RevisionDemo2026!';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Los ejemplos de revisión solo se permiten en local o testing.');
        }
        DB::transaction(function () {
            $admin = $this->cuenta('admin', 'Administrador demo', 'administrador');
            $autor = $this->cuenta('autor', 'Autora demo', 'usuario');
            $deshabilitado = $this->cuenta('deshabilitado', 'Autor deshabilitado demo', 'usuario', false);
            $lector = $this->cuenta('lector', 'Lector demo', 'usuario');
            $categoria = Categoria::firstOrCreate(['nombre' => 'Comidas']);
            $arroz = Ingrediente::firstOrCreate(['nombre' => 'Arroz']);
            $zanahoria = Ingrediente::firstOrCreate(['nombre' => 'Zanahoria']);
            $casos = [
                ['publicacion', 'pendiente', $autor, 'Arroz de estudiante'],
                ['correccion', 'pendiente', $autor, 'Arroz con verduras'],
                ['publicacion', 'pendiente', $deshabilitado, 'Arroz del domingo'],
                ['publicacion', 'aprobada', $autor, 'Arroz casero'],
                ['publicacion', 'rechazada', $autor, 'Arroz rápido'],
                ['publicacion', 'cancelada', $autor, 'Arroz para compartir'],
            ];
            foreach ($casos as $indice => [$tipo, $estado, $cuenta, $nombre]) {
                $clave = sprintf('b5c876a1-8a7b-4c22-9120-%012d', $indice + 1);
                if (SolicitudRevision::where('clave_idempotencia', $clave)->exists()) {
                    continue;
                }
                $receta = Receta::factory()->create([
                    'nombre' => '[DEMO] '.$nombre, 'creado_por' => $cuenta->id,
                    'publicada_en' => $tipo === 'correccion' ? now()->subDays(7) : null,
                    'descripcion' => 'Una receta sencilla de arroz con verduras para compartir.',
                    'tips' => 'Lava el arroz antes de cocinar.',
                ]);
                $imagen = 'recetas/'.$receta->id.'/demo.png';
                if (! Storage::disk('local')->put($imagen, file_get_contents(database_path('seeders/fixtures/receta-demo.png')))) {
                    throw new RuntimeException('No se pudo guardar la imagen privada de demostración.');
                }
                $receta->update(['imagen' => $imagen]);
                $receta->categorias()->attach($categoria->id);
                $receta->ingredientes()->attach([
                    $arroz->id => ['cantidad' => 1, 'unidad' => 'taza', 'notas' => 'Lavado', 'orden' => 1],
                    $zanahoria->id => ['cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'En cubos', 'orden' => 2],
                ]);
                $receta->pasos()->createMany([
                    ['orden' => 1, 'instruccion' => 'Lava y corta la zanahoria.'],
                    ['orden' => 2, 'instruccion' => 'Cocina el arroz con las verduras durante 20 minutos.'],
                ]);
                $contenido = [
                    'nombre' => '[DEMO] '.$nombre,
                    'descripcion' => 'Arroz con verduras frescas, listo para compartir en casa.',
                    'imagen' => $imagen, 'porciones' => 3, 'tiempo_preparacion' => 30,
                    'tips' => $tipo === 'correccion' ? 'Deja reposar cinco minutos antes de servir.' : null,
                    'categorias' => [$categoria->id],
                    'ingredientes' => [
                        ['ingrediente_id' => $zanahoria->id, 'cantidad' => 2, 'unidad' => 'unidades', 'notas' => 'Cortadas en cubos pequeños', 'orden' => 1],
                        ['ingrediente_id' => $arroz->id, 'cantidad' => 1.5, 'unidad' => 'tazas', 'notas' => 'Lavado', 'orden' => 2],
                    ],
                    'pasos' => [
                        ['orden' => 1, 'instruccion' => 'Enjuaga el arroz y escurre el agua.'],
                        ['orden' => 2, 'instruccion' => 'Lava y corta la zanahoria.'],
                        ['orden' => 3, 'instruccion' => 'Cocina el arroz con las verduras durante 20 minutos.'],
                    ],
                ];
                $solicitud = SolicitudRevision::factory()->create([
                    'receta_id' => $receta->id, 'solicitado_por' => $cuenta->id, 'tipo' => $tipo,
                    'contenido' => $contenido, 'clave_idempotencia' => $clave, 'created_at' => now()->subDays(2)->addMinutes($indice),
                ]);
                if ($estado === 'aprobada' || $estado === 'rechazada') {
                    app(ProcesarRevision::class)->ejecutar($admin, $solicitud, $estado === 'aprobada' ? 'aprobar' : 'rechazar', 'Indica con más precisión el tiempo de cocción.');
                } elseif ($estado === 'cancelada') {
                    $solicitud->forceFill(['estado' => 'cancelada', 'cancelada_en' => now()])->save();
                }
                if ($tipo === 'correccion') {
                    Valoracion::factory()->create(['receta_id' => $receta->id, 'usuario_id' => $lector->id, 'puntuacion' => 4]);
                    $receta->usuariosQueLaGuardaron()->attach($lector->id);
                }
            }
        });
        $this->command?->info('Ejemplos listos. Acceso local: admin@revision-demo.invalid / '.self::PASSWORD);
        $this->command?->info('Repetir este seeder conserva las decisiones tomadas y no modifica cuentas existentes.');
    }

    private function cuenta(string $alias, string $nombre, string $rol, bool $activo = true): User
    {
        $email = $alias.'@revision-demo.invalid';
        $existente = User::where('email', $email)->first();
        if ($existente) {
            if ($existente->name !== $nombre || ! Hash::check(self::PASSWORD, $existente->password)) {
                throw new RuntimeException('El correo reservado '.$email.' ya pertenece a otra cuenta. No se modificó.');
            }

            return $existente;
        }

        return User::factory()->create([
            'name' => $nombre, 'email' => $email, 'password' => self::PASSWORD,
            'rol' => $rol, 'activo' => $activo,
        ]);
    }
}

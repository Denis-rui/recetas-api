<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarCategorias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:limpiar-categorias';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina categorías extra y reasigna sus recetas a las 4 categorías principales existentes sin duplicar datos ni crear recetas nuevas.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando limpieza y reasignación de categorías...');

        // 1. Verificar que las 4 categorías principales existan en la base de datos
        // Usamos where()->first() para no crear NINGÚN registro nuevo
        $nombresPrincipales = ['Comidas', 'Bebidas', 'Cócteles', 'Postres'];
        $categoriasPrincipales = [];

        foreach ($nombresPrincipales as $nombre) {
            $cat = Categoria::where('nombre', $nombre)->first();
            if (! $cat) {
                $this->error("La categoría principal '{$nombre}' NO existe en la base de datos. Debes asegurarte de que existan antes de correr este comando.");

                return self::FAILURE;
            }
            $categoriasPrincipales[$nombre] = $cat;
        }

        // 2. Definir el mapeo de categorías a eliminar -> categoría destino
        // Estas categorías fueron extraídas del seeder RecetasPeruanasSeeder
        $mapeo = [
            'Entradas' => 'Comidas',
            'Platos de Fondo' => 'Comidas',
            'Piqueos' => 'Comidas',
            'Pastas' => 'Comidas',
            'Entradas Calientes' => 'Comidas',
            'Sopas' => 'Comidas',
        ];

        DB::transaction(function () use ($mapeo, $categoriasPrincipales) {
            foreach ($mapeo as $catAntiguaNombre => $catNuevaNombre) {
                $catAntigua = Categoria::where('nombre', $catAntiguaNombre)->first();
                $catNueva = $categoriasPrincipales[$catNuevaNombre] ?? null;

                if ($catAntigua && $catNueva) {
                    $this->info("Procesando recetas de '{$catAntiguaNombre}' para moverlas a '{$catNuevaNombre}'...");

                    // Obtener SOLO las recetas vinculadas a la categoría antigua (no se crean nuevas)
                    $recetas = $catAntigua->recetas;
                    $contador = 0;

                    foreach ($recetas as $receta) {
                        // syncWithoutDetaching SOLO agrega la relación a la nueva categoría
                        // en la tabla pivote si es que no la tiene ya. No duplica datos.
                        $receta->categorias()->syncWithoutDetaching([$catNueva->id]);

                        // Quitamos la relación con la categoría antigua
                        $receta->categorias()->detach($catAntigua->id);
                        $contador++;
                    }

                    // Una vez desvinculadas sus recetas, eliminamos la categoría extra
                    $catAntigua->delete();

                    $this->info("✓ Se actualizaron {$contador} recetas. Categoría '{$catAntiguaNombre}' eliminada.");
                } else {
                    if (! $catAntigua) {
                        $this->warn("- La categoría '{$catAntiguaNombre}' no se encontró en la BD (quizás ya fue eliminada o no tiene ese nombre exacto).");
                    }
                }
            }
        });

        $this->info('Limpieza y reasignación completada exitosamente.');

        return self::SUCCESS;
    }
}
